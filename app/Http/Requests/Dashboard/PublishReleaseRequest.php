<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishReleaseRequest extends FormRequest
{
    /**
     * No auth guard on the dashboard yet — see docs/releases.md.
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
            'version' => ['required', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
