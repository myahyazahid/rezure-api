<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily snapshot of one release asset's cumulative GitHub download_count.
 */
class GithubReleaseDownload extends Model
{
    protected $fillable = [
        'date',
        'version',
        'asset_name',
        'download_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'download_count' => 'integer',
        ];
    }
}
