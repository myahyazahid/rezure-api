<?php

namespace App\Http\Requests\Dashboard;

use App\Support\BlogImageFile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class BlogImageRequest extends FormRequest
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
     * The size limit is in kilobytes and matches `BlogImageFile::MAX_BYTES`.
     * What the file actually is gets decided from its bytes by
     * `BlogImageFile`, not from its name or the MIME type the browser claimed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'max:'.intdiv(BlogImageFile::MAX_BYTES, 1024),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    try {
                        BlogImageFile::detectFormat((string) $value->get());
                    } catch (InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                },
            ],
        ];
    }
}
