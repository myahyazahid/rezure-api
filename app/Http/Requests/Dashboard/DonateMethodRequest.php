<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DonateMethodRequest extends FormRequest
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
     * `url` matters for local/global, `symbol`/`address` for crypto — which
     * pair is required depends on `category`, validated either way so a
     * malformed request (e.g. a tampered `category`) can't create a method
     * missing the field its own category needs.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'in:local,global,crypto'],
            'preset' => ['nullable', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required_if:category,local,global', 'nullable', 'url', 'max:2048'],
            'symbol' => ['required_if:category,crypto', 'nullable', 'string', 'max:20'],
            'address' => ['required_if:category,crypto', 'nullable', 'string', 'max:200'],
        ];
    }
}
