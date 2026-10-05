<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DonateConfig;
use App\Support\QrisFile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class UpdateDonateQrisRequest extends FormRequest
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
     * The size limit is in kilobytes and matches `DonateConfig::MAX_QRIS_BYTES`.
     * What the file actually is gets decided from its bytes by `QrisFile`, not
     * from its name or the MIME type the browser claimed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'qris' => [
                'required',
                'file',
                'max:'.intdiv(DonateConfig::MAX_QRIS_BYTES, 1024),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    try {
                        QrisFile::detectFormat((string) $value->get());
                    } catch (InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                },
            ],
        ];
    }
}
