<?php

namespace App\Models;

use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use HasFactory;

    /**
     * `MAJOR.MINOR.PATCH` only — no `v` prefix, no pre-release suffix. It's
     * what `tauri-plugin-updater` parses on the client (a version it can't
     * read turns the whole update check into an error), and the major is
     * what decides which line a release belongs to.
     */
    public const VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';

    protected $fillable = [
        'version',
        'notes',
        'signature',
        'download_url',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * The major a version string belongs to (`3` for `3.0.2`), or `null`
     * when it doesn't start with one.
     */
    public static function majorOf(string $version): ?int
    {
        return preg_match('/^(\d+)\./', $version, $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * The newest release of one major line, or of every line when `$major`
     * is null — highest version first, not latest `published_at`. Several
     * lines are maintained side by side (3.x, 4.x, ...), so a 3.0.2 hotfix
     * published after 4.0.0 must not become the release everyone sees.
     */
    public static function current(?int $major = null): ?self
    {
        return static::query()
            ->when($major !== null, fn (Builder $query) => $query->where('version', 'like', $major.'.%'))
            ->get()
            ->sort(static::newestFirst(...))
            ->first();
    }

    /**
     * The newest release of each major line, highest line first.
     *
     * @return Collection<int, self>
     */
    public static function currentPerLine(): Collection
    {
        return static::query()
            ->get()
            ->filter(fn (self $release): bool => $release->major !== null)
            ->sort(static::newestFirst(...))
            ->unique(fn (self $release): int => $release->major)
            ->values();
    }

    /**
     * Republishing a version (to fix its notes) adds a row rather than
     * editing one, so between two rows of the same version the later one
     * wins.
     */
    private static function newestFirst(self $a, self $b): int
    {
        return version_compare($b->version, $a->version) ?: $b->published_at <=> $a->published_at;
    }

    /**
     * @return Attribute<?int, never>
     */
    protected function major(): Attribute
    {
        return Attribute::get(fn (): ?int => static::majorOf($this->version));
    }
}
