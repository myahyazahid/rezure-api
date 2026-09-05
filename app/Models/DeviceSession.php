<?php

namespace App\Models;

use Database\Factories\DeviceSessionFactory;
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
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
