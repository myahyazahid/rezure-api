<?php

namespace App\Http\Requests\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TicketHistoryRequest extends FormRequest
{
    /**
     * No user auth here — device_id is the identifier, and every payload
     * is eligible; see CLAUDE.md on lightweight, device-keyed auth.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
        ];
    }
}
