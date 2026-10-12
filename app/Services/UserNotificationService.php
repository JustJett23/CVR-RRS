<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class UserNotificationService
{
    /** @param array<string, mixed> $data */
    public function send(User $user, string $type, string $title, string $message, array $data = []): ?UserNotification
    {
        $preferences = $user->preferences ?? [];
        $preferenceKey = str_starts_with($type, 'equipment_request.') ? 'equipment_updates' : 'reservation_updates';

        if (isset($preferences['in_app_notifications']) && ! filter_var($preferences['in_app_notifications'], FILTER_VALIDATE_BOOL)
            || isset($preferences[$preferenceKey]) && ! filter_var($preferences[$preferenceKey], FILTER_VALIDATE_BOOL)) {
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
