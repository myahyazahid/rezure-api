<?php

namespace App\Http\Requests\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketRequest extends FormRequest
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
     * `os_version` is the client's full Windows product name (e.g. "Windows
     * 11 IoT Enterprise LTSC 2021"), which runs past 32 characters on some
     * editions — hence 64, matching the heartbeat's `os`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
            'client_ticket_id' => ['required', 'uuid'],
            'category' => ['required', 'string', Rule::in(['bug', 'feature_request', 'general'])],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'os_version' => ['nullable', 'string', 'max:64'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:png,jpg,jpeg,gif,webp,txt,log,zip', 'max:10240'],
        ];
    }
}
