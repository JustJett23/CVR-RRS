<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountSettingsRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('pages.settings');
    }

    public function update(UpdateAccountSettingsRequest $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $preferences = array_replace($user->preferences ?? [], $data['preferences'] ?? []);
        foreach (['in_app_notifications', 'reservation_updates', 'equipment_updates'] as $booleanPreference) {
            if (array_key_exists($booleanPreference, $preferences)) {
                $preferences[$booleanPreference] = filter_var($preferences[$booleanPreference], FILTER_VALIDATE_BOOL);
            }
        }
        unset($data['preferences']);
        $newPassword = $data['new_password'] ?? null;
        unset($data['current_password'], $data['new_password'], $data['new_password_confirmation']);
        if ($newPassword !== null) {
            $data['password'] = $newPassword;
        }
        $user->update([...$data, 'preferences' => $preferences]);
        $activityLogger->record($user, 'account.settings_updated', $user, ['fields' => array_keys($data)]);

        return back()->with('status', 'Your account settings have been saved.');
    }
}
