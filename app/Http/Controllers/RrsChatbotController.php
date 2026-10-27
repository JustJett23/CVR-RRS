<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RrsChatbotController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:240'],
        ]);

        if ($this->containsPersonalInformation($validated['question'])) {
            throw ValidationException::withMessages([
                'question' => 'Please remove personal details and ask again using a general RRS question.',
            ]);
        }

        $baseUrl = (string) config('services.rrs_ai.url');
        $token = (string) config('services.rrs_ai.token');

        if ($baseUrl === '' || $token === '') {
            return response()->json([
                'message' => 'The RRS help assistant is not configured right now. Please try again later.',
            ], 503);
        }

        $serviceUrl = rtrim($baseUrl, '/');
        $chatUrl = str_ends_with($serviceUrl, '/api/chat')
            ? $serviceUrl
            : (str_ends_with($serviceUrl, '/api') ? $serviceUrl.'/chat' : $serviceUrl.'/api/chat');

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->connectTimeout(3)
                ->timeout(45)
                ->post($chatUrl, [
                    'question' => $validated['question'],
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'The RRS help assistant is temporarily unavailable. Please try again shortly.',
            ], 503);
        }

        if (! $response->successful() || ! is_string($response->json('answer'))) {
            return response()->json([
                'message' => 'The RRS help assistant could not answer that right now. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'answer' => mb_substr($response->json('answer'), 0, 1200),
        ]);
    }

    private function containsPersonalInformation(string $question): bool
    {
        return preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $question) === 1
            || preg_match('/(?:\+?\d[\d\s().-]{8,}\d)/', $question) === 1
            || preg_match('/\b\d{7,}\b/', $question) === 1;
    }
}
