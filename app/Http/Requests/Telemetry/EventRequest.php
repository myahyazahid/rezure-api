<?php

namespace App\Http\Requests\Telemetry;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
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
            'event_id' => ['required', 'uuid'],
            'event_type' => ['required', 'string', 'max:64'],
            'event_name' => ['nullable', 'string', 'max:128'],
            'app_version' => ['required', 'string', 'max:32'],
            'payload' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
