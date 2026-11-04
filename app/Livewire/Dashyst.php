<?php

namespace App\Livewire;

use App\Models\AiSetting;
use App\Models\Server;
use App\Services\AiAssistant;
use Livewire\Component;

/**
 * Compact always-available assistant. It proposes one action at a time and
 * nothing runs until the operator approves it.
 */
class Dashyst extends Component
{
    public bool $open = false;

    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public string $prompt = '';

    public string $provider = 'openai';

    public string $model = 'gpt-4o-mini';

    public ?string $pendingCommand = null;

    public string $serverUuid = '';

    public ?string $error = null;

    /** @var array{question: string, options: array<int, string>, votes: array<int, int>}|null */
    public ?array $poll = null;

    public function mount(): void
    {
        if (! auth()->check()) {
            return;
        }

        $settings = AiSetting::forTeam(currentTeam()->id);
        $this->provider = $settings->provider;
        $this->model = $settings->model;
        $this->serverUuid = (string) (Server::query()->where('team_id', currentTeam()->id)->value('uuid') ?? '');
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function updatedProvider(string $provider): void
    {
        $entry = collect(config('ai_providers.providers', []))->firstWhere('id', $provider);

        if (is_array($entry) && ! empty($entry['models'])) {
            $this->model = (string) $entry['models'][0];
        }
    }

    public function send(): void
    {
        $this->validate(['prompt' => ['required', 'string', 'max:2000']]);
        $this->error = null;
        $this->pendingCommand = null;

        $messages = array_merge($this->messages, [['role' => 'user', 'content' => $this->prompt]]);
        $result = (new AiAssistant(AiSetting::forTeam(currentTeam()->id)))->ask($messages, $this->context());

        $this->messages = array_slice(array_merge($messages, [
            ['role' => 'assistant', 'content' => $result['reply']],
        ]), -20);
        $this->pendingCommand = $result['command'];
        $this->prompt = '';
    }

    public function approve(): void
    {
        $this->error = null;

        $settings = AiSetting::forTeam(currentTeam()->id);

        if (! $settings->allow_command_execution || ! auth()->user()?->isAdminOfTeam(currentTeam()->id)) {
            $this->error = 'Running commands is disabled for this team, or you are not an admin.';

            return;
        }

        if ($this->pendingCommand === null) {
            return;
        }

        $server = Server::query()->where('team_id', currentTeam()->id)->where('uuid', $this->serverUuid)->first();

        if ($server === null) {
            $this->error = 'Choose a server first.';

            return;
        }

        try {
            $output = (string) instant_remote_process([$this->pendingCommand], $server, false, false, 120);
            $this->messages[] = ['role' => 'assistant', 'content' => "Ran on {$server->name}:\n".$output];
            $this->pendingCommand = null;
            auditLog('ui.dashyst.command_run', [
                'team_id' => currentTeam()->id,
                'resource' => 'dashyst',
                'server' => $server->uuid,
            ]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function reject(): void
    {
        $this->pendingCommand = null;
        $this->messages[] = ['role' => 'assistant', 'content' => 'Action cancelled.'];
    }

    /**
     * Starts a small local poll. Format: "Question? | option | option".
     */
    public function startPoll(): void
    {
        $parts = array_values(array_filter(array_map('trim', explode('|', $this->prompt))));

        if (count($parts) < 3) {
            $this->error = 'Write the question, then at least two options separated by |.';

            return;
        }

        $question = array_shift($parts);
        $this->poll = ['question' => (string) $question, 'options' => array_slice($parts, 0, 5), 'votes' => array_fill(0, min(5, count($parts)), 0)];
        $this->prompt = '';
        $this->error = null;
    }

    public function vote(int $index): void
    {
        if ($this->poll !== null && isset($this->poll['votes'][$index])) {
            $this->poll['votes'][$index]++;
        }
    }

    public function closePoll(): void
    {
        $this->poll = null;
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->pendingCommand = null;
        $this->error = null;
    }

    public function render()
    {
        $providers = config('ai_providers.providers', []);
        $current = collect($providers)->firstWhere('id', $this->provider);

        return view('livewire.dashyst', [
            'providers' => $providers,
            'currentProvider' => is_array($current) ? $current : null,
            'providerModels' => is_array($current) ? ($current['models'] ?? []) : [],
            'servers' => auth()->check() ? Server::query()->where('team_id', currentTeam()->id)->get() : collect(),
        ]);
    }

    private function context(): string
    {
        $team = currentTeam();

        return 'Team: '.($team?->name ?? 'unknown')
            .'. Servers: '.Server::query()->where('team_id', $team?->id)->limit(20)->pluck('name')->implode(', ');
    }
}
