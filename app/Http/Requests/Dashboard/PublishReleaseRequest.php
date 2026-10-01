<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Release;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishReleaseRequest extends FormRequest
{
    /**
     * Authorization is handled by the 'auth' route middleware on the
     * dashboard group, not here — every request reaching this class already
     * belongs to a logged-in user.
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
            'version' => ['required', 'string', 'max:32', 'regex:'.Release::VERSION_PATTERN],
            'notes' => ['nullable', 'string', 'max:4000'],
            'signature' => ['nullable', 'string', 'max:1000', 'required_with:download_url'],
            'download_url' => ['nullable', 'url', 'max:2048', 'required_with:signature'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.regex' => 'Use MAJOR.MINOR.PATCH, e.g. 3.0.1 — no "v" prefix. The client\'s updater can\'t read anything else.',
        ];
    }
}
