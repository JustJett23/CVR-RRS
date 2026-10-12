<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\VerifyIdRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'errors' => ['email' => ['The provided credentials are incorrect.']],
            ], 422)->header('Cache-Control', 'no-store, private');
        }

        if (! $request->user()->is_active) {
            Auth::logout();

            return response()->json([
                'message' => 'This account is inactive.',
                'errors' => ['email' => ['This account is inactive.']],
            ], 403)->header('Cache-Control', 'no-store, private');
        }

        $request->session()->regenerate();

        return response()->json(['authenticated' => true])->header('Cache-Control', 'no-store, private');
    }

    public function verifyId(VerifyIdRequest $request): JsonResponse
    {
        $data = $request->validated();
        $existingUser = User::query()
            ->where('student_number', $data['scanned_student_number'])
            ->first();

        if ($existingUser !== null) {
            if (! $existingUser->is_active) {
                return response()->json([
                    'message' => 'This account is inactive. Sign in with your NPC institutional email or contact campus support.',
                ], 403)->header('Cache-Control', 'no-store, private');
            }

            if ($this->normalizeCourse($existingUser->course) !== $this->normalizeCourse($data['scanned_course'])
                || $this->normalizeContactNumber($existingUser->contact_number) !== $this->normalizeContactNumber($data['scanned_contact'])) {
                return response()->json([
                    'message' => 'An account exists for this student number, but the scanned course or contact number does not match. Sign in with your NPC institutional email instead.',
                ], 409)->header('Cache-Control', 'no-store, private');
            }

            Auth::login($existingUser, $request->boolean('remember'));
            $request->session()->regenerate();
            $request->session()->flash('onboarding_notification', 'NPC ID verified. Welcome back.');

            return response()->json(['authenticated' => true])
                ->header('Cache-Control', 'no-store, private');
        }

        $email = $data['email'] ?? $this->institutionalEmail($data['scanned_name'], $data['scanned_student_number']);

        if (User::query()->where('email', $email)->exists()) {
            return response()->json([
                'message' => 'That NPC institutional email is already linked to another account. Sign in with your institutional email instead.',
            ], 409)->header('Cache-Control', 'no-store, private');
        }

        $password = $data['password'] ?? Str::random(16);

        try {
            $user = DB::transaction(function () use ($data, $email, $password): User {
                $user = User::query()->create([
                    'name' => $data['scanned_name'],
                    'student_number' => $data['scanned_student_number'],
                    'course' => $data['scanned_course'],
                    'contact_number' => $data['scanned_contact'],
                    'emergency_contact' => $data['emergency_contact'],
                    'organization_id' => strtoupper($data['organization_id'] ?? 'ICS'),
                    'qr_token' => 'CVE-'.Str::upper(Str::random(24)),
                    'onboarding_verified_at' => now(),
                    'email' => $email,
                    'password' => $password,
                    'role' => 'student',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $payload = [
                    'email' => $email,
                    'temporary_password' => $password,
                    'organization_id' => $user->organization_id,
                    'qr_payload' => $email.'|'.$password,
                    'qr_token' => $user->qr_token,
                ];

                foreach ([
                    [
                        'user_id' => $user->id,
                        'type' => 'credentials',
                        'title' => 'Email and temporary password ready',
                        'message' => 'Email: '.$email.' · Temporary password: '.$password,
                        'data' => ['email' => $email],
                        'secure_payload' => Crypt::encryptString(json_encode(['email' => $email, 'temporary_password' => $password], JSON_THROW_ON_ERROR)),
                    ],
                    [
                        'user_id' => $user->id,
                        'type' => 'organization_id',
                        'title' => 'Organization ID generated',
                        'message' => 'Organization ID: '.$user->organization_id,
                        'data' => ['organization_id' => $user->organization_id],
                        'secure_payload' => Crypt::encryptString(json_encode(['organization_id' => $user->organization_id], JSON_THROW_ON_ERROR)),
                    ],
                    [
                        'user_id' => $user->id,
                        'type' => 'qr_access',
                        'title' => 'Personal QR access code ready',
                        'message' => 'QR access code: '.$user->qr_token,
                        'data' => ['qr_token' => $user->qr_token],
                        'secure_payload' => Crypt::encryptString(json_encode(['qr_payload' => $payload['qr_payload'], 'qr_token' => $user->qr_token], JSON_THROW_ON_ERROR)),
                    ],
                ] as $notification) {
                    UserNotification::query()->create($notification);
                }

                return $user;
            }, 3);
        } catch (QueryException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            $isUniqueViolation = $sqlState === '23505'
                || ($sqlState === '23000' && in_array($driverCode, [1062, 1555, 2067], true));

            if ($isUniqueViolation) {
                return response()->json([
                    'message' => 'An account already exists for this student number or institutional email.',
                ], 409);
            }

            Log::error('Student ID verification database operation failed.', [
                'sql_state' => $sqlState,
                'driver_code' => $driverCode,
            ]);

            return response()->json([
                'message' => 'Account verification is temporarily unavailable. Please try again shortly.',
            ], 503)->header('Cache-Control', 'no-store, private');
        } catch (Throwable $exception) {
            Log::error('Student ID verification failed unexpectedly.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Account verification could not be completed. Your details are still available; retry shortly or use institutional email sign-in.',
            ], 503)->header('Cache-Control', 'no-store, private');
        }
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $emailMessage = $request->filled('email') ? 'institutional email accepted' : 'institutional email generated';
        $request->session()->flash('onboarding_notification', 'NPC ID verified. Personal QR token created, '.$emailMessage.', and your temporary password was securely saved.');

        return response()->json(['authenticated' => true])
            ->header('Cache-Control', 'no-store, private');
    }

    private function normalizeCourse(?string $course): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $course)) ?? '');
    }

    private function normalizeContactNumber(?string $contact): string
    {
        $digits = preg_replace('/\D+/', '', (string) $contact) ?? '';

        if (str_starts_with($digits, '0063')) {
            return '0'.substr($digits, 4);
        }

        if (str_starts_with($digits, '63')) {
            return '0'.substr($digits, 2);
        }

        if (preg_match('/^9\d{9}$/', $digits) === 1) {
            return '0'.$digits;
        }

        return $digits;
    }

    private function institutionalEmail(string $name, string $studentNumber): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $last = strtolower((string) array_pop($parts));
        $initials = implode('', array_map(static fn (string $part): string => strtolower($part[0]), $parts));

        return $initials.$last.$studentNumber.'@navotaspolytechniccollege.edu.ph';
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

