<?php

namespace App\Models;

use Database\Factories\ChangelogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Changelog extends Model
{
    /** @use HasFactory<ChangelogFactory> */
    use HasFactory;

    protected $fillable = [
        'version',
        'title',
        'body',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
        ];
    }
}
