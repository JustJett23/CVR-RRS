<?php

namespace App\Http\Requests;

use App\Models\EquipmentRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelEquipmentRequestRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $equipmentRequest = $this->route('equipmentRequest');
        $user = $this->user();

        return $user?->is_active === true
            && $equipmentRequest instanceof EquipmentRequest
            && ($equipmentRequest->requested_by === $user->getKey() || in_array($user->role, ['admin', 'creator'], true));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
