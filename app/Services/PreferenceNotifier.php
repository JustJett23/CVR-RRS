<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class PreferenceNotifier
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(User $user, string $preference, string $type, string $title, string $message, array $data = []): ?UserNotification
    {
        if (! (bool) ($user->preferences[$preference] ?? true)) {
            return null;
        }

        return $user->userNotifications()->create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
