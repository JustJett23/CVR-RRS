// <?php

// namespace App\Http\Requests;

// use Illuminate\Contracts\Validation\ValidationRule;
// use Illuminate\Foundation\Http\FormRequest;
// use Illuminate\Support\Str;

// class VerifyIdRequest extends FormRequest
// {
//     protected function prepareForValidation(): void
//     {
//         $studentNumber = $this->digitsOnly($this->input('scanned_student_number'));
//         $contact = $this->digitsOnly($this->input('scanned_contact'));

//         if (Str::startsWith($contact, '0063')) {
//             $contact = substr($contact, 4);
//         } elseif (Str::startsWith($contact, '63')) {
//             $contact = substr($contact, 2);
//         }

//         if (preg_match('/^9\d{9}$/', $contact) === 1) {
//             $contact = '0'.$contact;
//         }

//         $this->merge([
//             'scanned_student_number' => $studentNumber,
//             'scanned_contact' => $contact,
//             'organization_id' => ($organizationId = strtoupper(trim((string) $this->input('organization_id')))) !== ''
//                 ? $organizationId
//                 : null,
//             'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
//         ]);
//     }

//     /**
//      * Determine if the user is authorized to make this request.
//      */
//     public function authorize(): bool
//     {
//         return true;
//     }

//     /**
//      * Get the validation rules that apply to the request.
//      *
//      * @return array<string, ValidationRule|array<mixed>|string>
//      */
//     public function rules(): array
//     {
//         return [
//             'scanned_name' => ['required', 'string', 'max:150'],
//             'scanned_course' => ['required', 'string', 'max:150'],
//             'scanned_student_number' => ['required', 'regex:/^[0-9]{6}$/'],
//             'scanned_contact' => ['required', 'regex:/^09\d{9}$/'],
//             'emergency_contact' => ['required', 'string', 'max:150'],
//             'organization_id' => ['nullable', 'string', 'max:50', 'regex:/^ICS$/i'],
//             'email' => ['nullable', 'email', 'regex:/^[^@\s]+@navotaspolytechniccollege\.edu\.ph$/i'],
//             'password' => ['nullable', 'string', 'min:1'],
//             'remember' => ['sometimes', 'boolean'],
//         ];
//     }

//     /**
//      * Keep malformed non-scalar request values from causing PHP cast warnings.
//      */
//     private function digitsOnly(mixed $value): string
//     {
//         if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
//             return '';
//         }

//         return preg_replace('/\D+/', '', (string) $value) ?? '';
//     }

//     /**
//      * @return array<string, string>
//      */
//     public function messages(): array
//     {
//         return [
//             'scanned_contact.required' => 'Enter your contact number manually if scanning did not read it.',
//             'scanned_contact.regex' => 'Enter a valid Philippine mobile number in 09XXXXXXXXX format.',
//         ];
//     }
// }

