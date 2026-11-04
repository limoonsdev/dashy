<?php

namespace App\Livewire\Assistant;

use App\Models\AiSetting;
use App\Models\Application;
use App\Models\Server;
use App\Services\AiAssistant;
use Livewire\Component;

class Index extends Component
{
    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public string $prompt = '';

    public ?string $proposedCommand = null;

    public string $serverUuid = '';

    public string $commandOutput = '';

    public ?string $error = null;

    public function mount(): void
    {
        $this->serverUuid = (string) (Server::query()->where('team_id', currentTeam()->id)->value('uuid') ?? '');
    }

    public function send(): void
    {
        $this->validate(['prompt' => ['required', 'string', 'max:4000']]);
        $this->error = null;
        $this->proposedCommand = null;

        $settings = AiSetting::forTeam(currentTeam()->id);
        $assistant = new AiAssistant($settings);

        $this->messages[] = ['role' => 'user', 'content' => $this->prompt];
        $result = $assistant->ask($this->messages, $this->context());

        $this->messages[] = ['role' => 'assistant', 'content' => $result['reply']];
        $this->proposedCommand = $result['command'];
        $this->prompt = '';
    }

    public function runCommand(): void
    {
        $settings = AiSetting::forTeam(currentTeam()->id);

        if (! $settings->allow_command_execution) {
            $this->error = 'Command execution is disabled for this team. Enable it in Settings → AI assistant.';

            return;
        }

        if (! auth()->user()?->isAdminOfTeam(currentTeam()->id)) {
            $this->error = 'Only a team admin or owner can run a proposed command.';

            return;
        }

        if ($this->proposedCommand === null) {
            return;
        }

        $server = Server::query()->where('team_id', currentTeam()->id)->where('uuid', $this->serverUuid)->first();

        if ($server === null) {
            $this->error = 'Choose a server first.';

            return;
        }

        try {
            $this->commandOutput = (string) instant_remote_process([$this->proposedCommand], $server);
            $this->messages[] = ['role' => 'assistant', 'content' => "Ran on {$server->name}:\n".$this->commandOutput];
            auditLog('ui.assistant.command_run', [
                'team_id' => currentTeam()->id,
                'resource' => 'assistant',
                'server' => $server->uuid,
            ]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->proposedCommand = null;
        $this->commandOutput = '';
        $this->error = null;
    }

    public function render()
    {
        return view('livewire.assistant.index', [
            'settings' => AiSetting::forTeam(currentTeam()->id),
            'servers' => Server::query()->where('team_id', currentTeam()->id)->get(),
        ]);
    }

    private function context(): string
    {
        $applications = Application::query()->where('team_id', currentTeam()->id)->limit(20)->pluck('name')->implode(', ');
        $servers = Server::query()->where('team_id', currentTeam()->id)->limit(20)->pluck('name')->implode(', ');

        return 'Servers: '.($servers ?: 'none').'. Applications: '.($applications ?: 'none').'.';
    }
}
