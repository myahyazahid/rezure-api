<?php

namespace App\Models;

use Database\Factories\DonateMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonateMethod extends Model
{
    /** @use HasFactory<DonateMethodFactory> */
    use HasFactory;

    /**
     * One row per configured donate button/wallet — `category` (`local`,
     * `global`, `crypto`) is which array of `GET /api/v1/support/donate`'s
     * response it serializes into, `preset` names which known platform it
     * was created from (see `App\Support\DonatePresets`), or `'custom'` for
     * a maintainer-typed one.
     */
    protected $fillable = [
        'category',
        'preset',
        'label',
        'url',
        'symbol',
        'address',
    ];
}
