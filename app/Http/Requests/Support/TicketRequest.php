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
            'os_version' => ['nullable', 'string', 'max:32'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:png,jpg,jpeg,gif,webp,txt,log,zip', 'max:10240'],
        ];
    }
}
