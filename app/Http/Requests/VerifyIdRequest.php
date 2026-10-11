<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class VerifyIdRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $studentNumber = preg_replace('/\D+/', '', (string) $this->input('scanned_student_number'));
        $contact = preg_replace('/\D+/', '', (string) $this->input('scanned_contact'));
        if (Str::startsWith($contact, '63')) {
            $contact = '0'.substr($contact, 2);
        }

        $this->merge([
            'scanned_student_number' => $studentNumber,
            'scanned_contact' => $contact,
            'organization_id' => ($organizationId = strtoupper(trim((string) $this->input('organization_id')))) !== ''
                ? $organizationId
                : null,
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scanned_name' => ['required', 'string', 'max:150'],
            'scanned_course' => ['required', 'string', 'max:150'],
            'scanned_student_number' => ['required', 'regex:/^[0-9]{6}$/', 'unique:users,student_number'],
            'scanned_contact' => ['required', 'regex:/^(?:09\d{9}|\+63\s*9\d{9})$/'],
            'emergency_contact' => ['required', 'string', 'max:150'],
            'organization_id' => ['nullable', 'string', 'max:50', 'regex:/^ICS$/i'],
            'email' => ['nullable', 'email', 'regex:/^[^@\s]+@navotaspolytechniccollege\.edu\.ph$/i', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:1'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
