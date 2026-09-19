<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Blog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'author_name',
        'author_avatar',
        'title',
        'slug',
        'excerpt',
        'content',
        'tags',
        'featured_image',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function buildLogs(): HasMany
    {
        return $this->hasMany(BlogBuildLog::class)->orderByDesc('created_at');
    }

    public function latestBuildLog(): HasOne
    {
        return $this->hasOne(BlogBuildLog::class)->latestOfMany();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function getAuthorAttribute(): array
    {
        return [
            'name' => $this->author_name ?? $this->user?->name ?? 'Muhammad Yahya Zahid',
            'avatar' => $this->author_avatar ?? 'https://github.com/myahyazahid.png',
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'tags' => $this->tags ?? [],
            'featured_image' => $this->featured_image,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'author' => $this->author,
        ];
    }
}