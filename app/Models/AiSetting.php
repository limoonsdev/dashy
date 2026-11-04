<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OpenAI-compatible endpoint the in-dashboard assistant talks to.
 */
class AiSetting extends Model
{
    public const PROVIDERS = [
        'openai' => 'OpenAI',
        'openrouter' => 'OpenRouter',
        'ollama' => 'Ollama / LM Studio (local)',
        'custom' => 'Custom OpenAI-compatible endpoint',
    ];

    protected $fillable = [
        'team_id',
        'provider',
        'base_url',
        'api_key',
        'model',
        'system_prompt',
        'allow_command_execution',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'api_key' => 'encrypted',
        'allow_command_execution' => 'boolean',
    ];

    public static function forTeam(int $teamId): self
    {
        return self::query()->firstOrCreate(['team_id' => $teamId], [
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'allow_command_execution' => false,
        ]);
    }

    public function configured(): bool
    {
        return filled($this->base_url) && filled($this->model) && ($this->provider === 'ollama' || filled($this->api_key));
    }
}
