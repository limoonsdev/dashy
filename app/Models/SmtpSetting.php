<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpSetting extends Model
{
    protected $fillable = [
        'team_id', 'enabled', 'host', 'port', 'username', 'password',
        'encryption', 'from_address', 'from_name',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'enabled' => 'boolean',
        'password' => 'encrypted',
    ];

    public static function forTeam(int $teamId): self
    {
        return self::query()->firstOrCreate(['team_id' => $teamId], [
            'host' => '127.0.0.1',
            'port' => 1025,
            'from_address' => 'dashy@localhost',
            'from_name' => 'Dashy',
        ]);
    }
}
