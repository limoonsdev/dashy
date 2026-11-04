<?php

namespace App\Livewire\Settings;

use App\Models\BotChannel;
use Livewire\Component;

class Bot extends Component
{
    public string $platform = 'discord';

    public bool $enabled = false;

    public string $botToken = '';

    public string $webhookSecret = '';

    public string $target = '';

    public string $embedColor = '#6b16ed';

    /** @var array<int, string> */
    public array $features = [];

    public function mount(): void
    {
        if (! isInstanceAdmin() && ! auth()->user()?->isAdminOfTeam(currentTeam()->id)) {
            abort(403);
        }

        $this->loadChannel();
    }

    public function setPlatform(string $platform): void
    {
        if (! array_key_exists($platform, BotChannel::PLATFORMS)) {
            return;
        }

        $this->platform = $platform;
        $this->loadChannel();
    }

    public function save(): void
    {
        $this->validate([
            'platform' => ['required', 'string', 'in:'.implode(',', array_keys(BotChannel::PLATFORMS))],
            'botToken' => ['nullable', 'string', 'max:4096'],
            'webhookSecret' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'string', 'max:255'],
            'embedColor' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'features' => ['array'],
        ]);

        $channel = BotChannel::forTeam(currentTeam()->id, $this->platform);
        $channel->fill([
            'enabled' => $this->enabled,
            'target' => $this->target,
            'embed_color' => $this->embedColor,
            'features' => array_values(array_intersect($this->features, array_keys(BotChannel::FEATURES))),
        ]);
        $channel->bot_token = $this->botToken;
        $channel->webhook_secret = $this->webhookSecret;
        $channel->save();

        auditLog('ui.settings.bot.updated', [
            'team_id' => currentTeam()->id,
            'resource' => 'bot',
            'platform' => $this->platform,
            'enabled' => $this->enabled,
        ]);

        $this->dispatch('success', 'Bot settings saved!');
    }

    public function render()
    {
        return view('livewire.settings.bot');
    }

    private function loadChannel(): void
    {
        $channel = BotChannel::forTeam(currentTeam()->id, $this->platform);

        $this->enabled = (bool) $channel->enabled;
        $this->botToken = (string) $channel->bot_token;
        $this->webhookSecret = (string) $channel->webhook_secret;
        $this->target = (string) $channel->target;
        $this->embedColor = (string) ($channel->embed_color ?: '#6b16ed');
        $this->features = (array) ($channel->features ?: array_keys(BotChannel::FEATURES));
    }
}
