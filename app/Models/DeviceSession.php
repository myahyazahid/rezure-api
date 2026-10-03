<?php

namespace App\Models;

use Database\Factories\DeviceSessionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSession extends Model
{
    /** @use HasFactory<DeviceSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'device_id',
        'client_session_id',
        'app_version',
        'started_at',
        'last_heartbeat_at',
        'ended_at',
        'duration_seconds',
        'country_code',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * How long the app was open in this session. `duration_seconds` is only
     * set by a clean close (the final heartbeat's `ended_at`); a session
     * that ended in a crash or force-kill falls back to its last heartbeat,
     * which undercounts by at most one 5-minute heartbeat interval.
     */
    protected function usageSeconds(): Attribute
    {
        return Attribute::get(fn (): int => (int) ($this->duration_seconds
            ?? max(0, $this->started_at->diffInSeconds($this->last_heartbeat_at))));
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
