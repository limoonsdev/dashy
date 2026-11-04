<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A sequence of steps the instance runs on demand, on a schedule, or from a webhook.
 */
class Workflow extends Model
{
    public const STEP_TYPES = [
        'command' => 'Run a shell command on a server',
        'http' => 'Call an HTTP endpoint',
        'deploy' => 'Deploy an application',
        'delay' => 'Wait',
    ];

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'description',
        'enabled',
        'trigger',
        'cron',
        'steps',
        'last_run_at',
        'last_status',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'steps' => 'array',
        'last_run_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $workflow) {
            $workflow->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function stepList(): array
    {
        return is_array($this->steps) ? array_values($this->steps) : [];
    }
}
