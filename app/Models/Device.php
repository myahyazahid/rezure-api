<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'device_id',
        'app_version',
        'os',
        'os_version',
        'telemetry_opted_out',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'telemetry_opted_out' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * The uuid isn't useful to a human at full length — the dashboard shows
     * this shortened, prefixed form instead (matches what the client's own
     * logs use for the same device).
     */
    protected function shortId(): Attribute
    {
        return Attribute::get(fn (): string => 'dev_'.substr(str_replace('-', '', $this->device_id), 0, 8));
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<DeviceSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(DeviceSession::class);
    }
}
