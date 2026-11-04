<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceSetting extends Model
{
    public const OPTIONS = [
        'docker' => 'Prune unused Docker images, containers and build cache (older than 7 days)',
        'journal' => 'Vacuum the systemd journal down to 7 days',
        'apt' => 'Clean the APT cache and remove unused packages',
        'tmp' => 'Delete files in /tmp older than 7 days',
        'logs' => 'Truncate log files larger than 50 MB',
    ];

    protected $fillable = [
        'team_id', 'daily_enabled', 'daily_time', 'weekly_enabled',
        'weekly_day', 'weekly_time', 'options', 'last_daily_at', 'last_weekly_at',
    ];

    protected $casts = [
        'daily_enabled' => 'boolean',
        'weekly_enabled' => 'boolean',
        'options' => 'array',
        'last_daily_at' => 'datetime',
        'last_weekly_at' => 'datetime',
    ];

    public static function forTeam(int $teamId): self
    {
        return self::query()->firstOrCreate(['team_id' => $teamId], [
            'options' => array_keys(self::OPTIONS),
        ]);
    }
}
