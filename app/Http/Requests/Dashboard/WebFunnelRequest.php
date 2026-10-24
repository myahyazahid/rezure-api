<?php

namespace App\Http\Requests\Dashboard;

use App\Models\WebFunnel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebFunnelRequest extends FormRequest
{
    /**
     * Authorization is handled by the 'auth' route middleware on the
     * dashboard group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The builder form always renders a fixed number of step rows; the
     * blank ones are dropped here so only filled steps are validated.
     */
    protected function prepareForValidation(): void
    {
        $steps = array_values(array_filter(
            (array) $this->input('steps', []),
            fn (mixed $step): bool => is_array($step) && trim((string) ($step['match'] ?? '')) !== '',
        ));

        $this->merge(['steps' => $steps]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'scope' => ['required', Rule::in([WebFunnel::SCOPE_SESSION, WebFunnel::SCOPE_VISITOR])],
            'window_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'steps' => ['required', 'array', 'min:2', 'max:8'],
            'steps.*.type' => ['required', Rule::in(['pageview', 'event'])],
            'steps.*.match' => ['required', 'string', 'max:512'],
            'steps.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'steps.min' => 'A funnel needs at least two steps.',
        ];
    }
}
