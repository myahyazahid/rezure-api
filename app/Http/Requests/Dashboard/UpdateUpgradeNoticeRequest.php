<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUpgradeNoticeRequest extends FormRequest
{
    /**
     * Its own bag, so a failed save doesn't pop open the publish form that
     * shares the Releases page (which opens on any default-bag error).
     */
    protected $errorBag = 'upgradeNotice';

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
     * Fields may stay blank while the notice is off, so a maintainer can
     * draft it ahead of a major release. `url` is limited to http(s) because
     * the client hands it straight to the system browser.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'major' => ['required_if_accepted:enabled', 'nullable', 'integer', 'min:1', 'max:999'],
            'message' => ['required_if_accepted:enabled', 'nullable', 'string', 'max:1000'],
            'url' => ['required_if_accepted:enabled', 'nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
