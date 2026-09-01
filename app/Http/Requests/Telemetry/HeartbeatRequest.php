<?php

namespace App\Http\Requests\Telemetry;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HeartbeatRequest extends FormRequest
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
            'session_id' => ['required', 'uuid'],
            'app_version' => ['required', 'string', 'max:32'],
            'os' => ['nullable', 'string', 'max:64'],
            'os_version' => ['nullable', 'string', 'max:32'],
            'occurred_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ];
    }
}
