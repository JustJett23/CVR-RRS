<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$this->user()->getKey()],
            'course' => ['nullable', 'string', 'max:120'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'preferences' => ['nullable', 'array'],
            'preferences.in_app_notifications' => ['sometimes', 'boolean'],
            'preferences.reservation_updates' => ['sometimes', 'boolean'],
            'preferences.equipment_updates' => ['sometimes', 'boolean'],
            'preferences.default_duration_minutes' => ['sometimes', 'integer', 'min:30', 'max:480'],
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password' => ['nullable', 'required_with:new_password_confirmation', 'string', 'min:12', 'confirmed'],
            'new_password_confirmation' => ['nullable', 'required_with:new_password', 'same:new_password'],
        ];
    }
}
