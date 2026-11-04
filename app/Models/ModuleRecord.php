<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One record of a workspace module: a calendar event, a document, a signature
 * request or a design. The payload shape depends on the module.
 */
class ModuleRecord extends Model
{
    public const MODULES = [
        'calendar' => 'Dashy Calendar',
        'sign' => 'Dashy Sign',
        'doc' => 'Dashy Doc',
        'design' => 'Dashy Design',
    ];

    protected $fillable = ['team_id', 'module', 'uuid', 'title', 'payload', 'status'];

    protected $casts = ['payload' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            $record->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
