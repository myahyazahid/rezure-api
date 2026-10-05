<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DonateMethod;
use App\Support\StickerFile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

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
     * `url` matters for local/global, `symbol`/`network`/`address` for
     * crypto — which set is required depends on `category`, validated either
     * way so a malformed request (e.g. a tampered `category`) can't create a
     * method missing the field its own category needs. `network` is required
     * because the same coin lives on several chains and an address sent on
     * the wrong one is usually lost.
     *
     * `icon` is optional and works for every category (a coin logo, GitHub's
     * mark, a Trakteer button's logo…). The size limit is in
     * kilobytes and matches `DonateMethod::MAX_ICON_BYTES`; what the file
     * actually is gets decided from its bytes by `StickerFile` (the same
     * PNG/WebP/SVG rules as stickers), not from its name or the MIME type
     * the browser claimed. `remove_icon` drops the current one on edit.
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
            'network' => ['required_if:category,crypto', 'nullable', 'string', 'max:60'],
            'address' => ['required_if:category,crypto', 'nullable', 'string', 'max:200'],
            'icon' => [
                'nullable',
                'file',
                'max:'.intdiv(DonateMethod::MAX_ICON_BYTES, 1024),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    try {
                        StickerFile::detectFormat((string) $value->get(), 'icons');
                    } catch (InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                },
            ],
            'remove_icon' => ['sometimes', 'boolean'],
        ];
    }
}
