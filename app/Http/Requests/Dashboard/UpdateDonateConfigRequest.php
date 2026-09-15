<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDonateConfigRequest extends FormRequest
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
     * The form posts a fixed number of rows per section so blank/unused
     * ones are expected — drop them before validation runs so an empty
     * trailing row never trips "required_with" on its sibling field.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'local' => $this->droppingBlankRows($this->array('local'), ['label', 'url']),
            'global' => $this->droppingBlankRows($this->array('global'), ['label', 'url']),
            'crypto' => $this->droppingBlankRows($this->array('crypto'), ['symbol', 'address']),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $requiredKeys
     * @return list<array<string, mixed>>
     */
    private function droppingBlankRows(array $rows, array $requiredKeys): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => collect($requiredKeys)->every(fn (string $key): bool => filled($row[$key] ?? null)),
        ));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],

            'local' => ['array'],
            'local.*.label' => ['required', 'string', 'max:100'],
            'local.*.url' => ['required', 'url', 'max:2048'],

            'global' => ['array'],
            'global.*.label' => ['required', 'string', 'max:100'],
            'global.*.url' => ['required', 'url', 'max:2048'],

            'crypto' => ['array'],
            'crypto.*.symbol' => ['required', 'string', 'max:20'],
            'crypto.*.label' => ['required', 'string', 'max:100'],
            'crypto.*.address' => ['required', 'string', 'max:200'],
        ];
    }
}
