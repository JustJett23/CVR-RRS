<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingsController extends Controller
{
    /**
     * @var array<string, mixed>
     */
    private const DEFAULT_PREFERENCES = [
        'default_reservation_duration' => 60,
        'default_organization_name' => '',
        'reservation_notifications' => true,
        'equipment_notifications' => true,
    ];

    public function index(Request $request): View
    {
        $user = $this->activeUser($request->user());

        return view('pages.settings', [
            'profile' => $user,
            'preferences' => [...self::DEFAULT_PREFERENCES, ...($user->preferences ?? [])],
            'isStudent' => $user->role === 'student',
        ]);
    }

    public function profile(Request $request): View
    {
        return view('pages.profile', [
            'profile' => $this->activeUser($request->user()),
        ]);
    }

    public function photo(Request $request): BinaryFileResponse
    {
        $user = $this->activeUser($request->user());
        $photoPath = $user->profile_photo_path;
        $expectedPrefix = 'profile-photos/'.$user->getKey().'/';
        $disk = Storage::disk('local');

        abort_unless(is_string($photoPath) && str_starts_with($photoPath, $expectedPrefix) && $disk->exists($photoPath), 404);

        return response()->file($disk->path($photoPath), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateProfile(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->activeUser($request->user());
        $rules = [
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^09\d{9}$/'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:30', 'regex:/^09\d{9}$/'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($user->onboarding_verified_at === null) {
            $rules = [
                'name' => ['required', 'string', 'max:150'],
                'course' => ['nullable', 'string', 'max:150'],
                ...$rules,
            ];
        }

        $data = $request->validate($rules);
        $profileData = [
            'contact_number' => $data['contact_number'] ?? null,
            'emergency_contact_name' => $data['guardian_name'] ?? null,
            'emergency_contact_phone' => $data['guardian_phone'] ?? null,
        ];

        $newPhotoPath = null;
        if ($request->hasFile('profile_photo')) {
            $newPhotoPath = $request->file('profile_photo')->store('profile-photos/'.$user->getKey(), 'local');

            if (! is_string($newPhotoPath)) {
                return back()->withErrors(['profile_photo' => 'The profile photo could not be saved. Please try again.'])->withInput();
            }

            $profileData['profile_photo_path'] = $newPhotoPath;
        }

        foreach (['name', 'course'] as $field) {
            if (array_key_exists($field, $data)) {
                $profileData[$field] = $data[$field];
            }
        }

        $changedFields = collect($profileData)->keys()->filter(fn (string $field): bool => $user->getAttribute($field) !== $profileData[$field])->values()->all();
        $oldPhotoPath = $user->profile_photo_path;
        $user->update($profileData);

        if ($newPhotoPath !== null && is_string($oldPhotoPath) && str_starts_with($oldPhotoPath, 'profile-photos/'.$user->getKey().'/')) {
            Storage::disk('local')->delete($oldPhotoPath);
        }

        if ($changedFields !== []) {
            $activityLogger->record($user, 'profile.updated', $user, ['fields' => $changedFields], $request->ip());
        }

        return to_route('profile')->with('status', 'Profile details saved.');
    }

    public function updatePassword(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->activeUser($request->user());
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user->update(['password' => $data['password']]);
        $activityLogger->record($user, 'account.password_updated', $user, ipAddress: $request->ip());

        return to_route('settings')->with('status', 'Password updated successfully.');
    }

    public function updatePreferences(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->activeUser($request->user());
        abort_unless($user->role !== 'student', 403);
        $data = $request->validate([
            'default_reservation_duration' => ['required', 'integer', 'in:60,120,180,240'],
            'default_organization_name' => ['nullable', 'string', 'max:255'],
            'reservation_notifications' => ['required', 'boolean'],
            'equipment_notifications' => ['required', 'boolean'],
        ]);
        $preferences = [...self::DEFAULT_PREFERENCES, ...($user->preferences ?? []), ...$data];
        $preferences['reservation_notifications'] = (bool) $data['reservation_notifications'];
        $preferences['equipment_notifications'] = (bool) $data['equipment_notifications'];
        $user->update(['preferences' => $preferences]);
        $activityLogger->record($user, 'preferences.updated', $user, [
            'default_reservation_duration' => $data['default_reservation_duration'],
            'reservation_notifications' => (bool) $data['reservation_notifications'],
            'equipment_notifications' => (bool) $data['equipment_notifications'],
        ], $request->ip());

        return to_route('settings')->with('status', 'Reservation preferences saved.');
    }

    private function activeUser(?User $user): User
    {
        abort_unless($user instanceof User && $user->is_active, 403);

        return $user;
    }
}
