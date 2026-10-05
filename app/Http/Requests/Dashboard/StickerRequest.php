<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Sticker;
use App\Support\StickerFile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StickerRequest extends FormRequest
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
     * `category` is stored as a slug (`girls`, `pixel-art`), so "Pixel Art"
     * typed into the form and `pixel-art` typed into the API are one category.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => Str::slug((string) $this->input('category')) ?: 'general',
        ]);
    }

    /**
     * The file is required to create a sticker and optional to edit one —
     * leaving it empty on edit keeps the image already stored. The size
     * limit is in kilobytes and matches `Sticker::MAX_BYTES`. What the file
     * actually is gets decided from its bytes by `StickerFile`, not from its
     * name or the MIME type the browser claimed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->route('sticker') === null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:40'],
            'is_published' => ['sometimes', 'boolean'],
            'file' => [
                $creating ? 'required' : 'nullable',
                'file',
                'max:'.intdiv(Sticker::MAX_BYTES, 1024),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    try {
                        StickerFile::detectFormat((string) $value->get());
                    } catch (InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                },
            ],
        ];
    }
}
