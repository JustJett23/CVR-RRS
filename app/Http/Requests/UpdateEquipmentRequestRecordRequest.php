<?php

namespace App\Http\Requests;

use App\Models\EquipmentRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentRequestRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $equipmentRequest = $this->route('equipmentRequest');

        return $this->user()?->is_active === true
            && $equipmentRequest instanceof EquipmentRequest
            && $equipmentRequest->requested_by === $this->user()->getKey()
            && $equipmentRequest->status === 'pending';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'reservation_id' => ['required', 'integer', 'exists:reservations,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
