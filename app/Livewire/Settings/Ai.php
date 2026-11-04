<?php

namespace App\Livewire\Settings;

use App\Models\AiSetting;
use Livewire\Component;

class Ai extends Component
{
    public string $provider = 'openai';

    public string $baseUrl = 'https://api.openai.com/v1';

    public string $apiKey = '';

    public string $model = 'gpt-4o-mini';

    public string $systemPrompt = '';

    public bool $allowCommandExecution = false;

    public function mount(): void
    {
        if (! isInstanceAdmin() && ! auth()->user()?->isAdminOfTeam(currentTeam()->id)) {
            abort(403);
        }

        $settings = AiSetting::forTeam(currentTeam()->id);
        $this->provider = $settings->provider;
        $this->baseUrl = (string) $settings->base_url;
        $this->apiKey = (string) $settings->api_key;
        $this->model = (string) $settings->model;
        $this->systemPrompt = (string) $settings->system_prompt;
        $this->allowCommandExecution = (bool) $settings->allow_command_execution;
    }

    public function updatedProvider(string $provider): void
    {
        $entry = $this->providerEntry($provider);

        if ($entry !== null && filled($entry['base'] ?? null)) {
            $this->baseUrl = (string) $entry['base'];
        }

        if ($entry !== null && ! empty($entry['models'])) {
            $this->model = (string) $entry['models'][0];

            return;
        }

        $this->baseUrl = match ($provider) {
            'openai' => 'https://api.openai.com/v1',
            'openrouter' => 'https://openrouter.ai/api/v1',
            'ollama' => 'http://localhost:11434/v1',
            default => $this->baseUrl,
        };
    }

    /** @return array<int, string> */
    private function providerIds(): array
    {
        $ids = array_values(array_filter(array_column(config('ai_providers.providers', []), 'id')));

        return $ids !== [] ? $ids : array_keys(AiSetting::PROVIDERS);
    }

    /** @return array<string, mixed>|null */
    private function providerEntry(string $provider): ?array
    {
        $entry = collect(config('ai_providers.providers', []))->firstWhere('id', $provider);

        return is_array($entry) ? $entry : null;
    }

    public function save(): void
    {
        $this->validate([
            'provider' => ['required', 'in:'.implode(',', $this->providerIds())],
            'baseUrl' => ['required', 'url', 'max:255'],
            'apiKey' => ['nullable', 'string', 'max:4096'],
            'model' => ['required', 'string', 'max:120'],
            'systemPrompt' => ['nullable', 'string', 'max:4000'],
        ]);

        $settings = AiSetting::forTeam(currentTeam()->id);
        $settings->fill([
            'provider' => $this->provider,
            'base_url' => $this->baseUrl,
            'model' => $this->model,
            'system_prompt' => $this->systemPrompt,
            'allow_command_execution' => $this->allowCommandExecution,
        ]);
        $settings->api_key = $this->apiKey;
        $settings->save();

        auditLog('ui.settings.ai.updated', [
            'team_id' => currentTeam()->id,
            'resource' => 'ai',
            'provider' => $this->provider,
            'allow_command_execution' => $this->allowCommandExecution,
        ]);

        $this->dispatch('success', 'Assistant settings saved!');
    }

    public function render()
    {
        $entry = $this->providerEntry($this->provider);

        return view('livewire.settings.ai', [
            'providers' => config('ai_providers.providers', []),
            'models' => $entry['models'] ?? [],
        ]);
    }
}
