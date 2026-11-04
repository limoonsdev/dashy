<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-team configuration of one chat platform the Dashy bot listens on.
 */
class BotChannel extends Model
{
    public const PLATFORMS = [
        'discord' => 'Discord',
        'telegram' => 'Telegram',
        'slack' => 'Slack',
        'whatsapp' => 'WhatsApp',
        'webhook' => 'Generic webhook',
    ];

    public const FEATURES = [
        'status' => 'Status and fleet summary',
        'deploy' => 'Deploy and restart applications',
        'lookup' => 'Domain, DNS and IP lookup',
        'notifications' => 'Deployment notifications',
        'approvals' => 'Ask before a destructive command',
    ];

    protected $fillable = [
        'team_id',
        'platform',
        'enabled',
        'bot_token',
        'webhook_secret',
        'target',
        'embed_color',
        'features',
    ];

    protected $hidden = [
        'bot_token',
        'webhook_secret',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'bot_token' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'features' => 'array',
    ];

    public static function forTeam(int $teamId, string $platform): self
    {
        return self::query()->firstOrNew([
            'team_id' => $teamId,
            'platform' => $platform,
        ], [
            'enabled' => false,
            'embed_color' => '#6b16ed',
            'features' => array_keys(self::FEATURES),
        ]);
    }

    public function label(): string
    {
        return self::PLATFORMS[$this->platform] ?? ucfirst($this->platform);
    }
}
