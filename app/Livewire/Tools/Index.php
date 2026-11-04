<?php

namespace App\Livewire\Tools;

use App\Models\Server;
use App\Services\Toolkit\ToolCatalog;
use Livewire\Component;

class Index extends Component
{
    public string $category = 'All';

    public string $serverUuid = '';

    public string $output = '';

    public string $error = '';

    public function mount(): void
    {
        $this->serverUuid = (string) (Server::query()->where('team_id', currentTeam()->id)->value('uuid') ?? '');
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
    }

    public function run(string $name): void
    {
        $this->error = $this->output = '';

        if (! auth()->user()?->isAdminOfTeam(currentTeam()->id)) {
            $this->error = 'Only a team admin can start a container on a server.';

            return;
        }

        $tool = collect(ToolCatalog::all())->firstWhere('name', $name);
        $server = Server::query()->where('team_id', currentTeam()->id)->where('uuid', $this->serverUuid)->first();

        if ($tool === null || $server === null) {
            $this->error = 'Pick a server first.';

            return;
        }

        try {
            $this->output = (string) instant_remote_process([$tool['run']], $server, false, false, 300);
            auditLog('ui.tools.started', ['team_id' => currentTeam()->id, 'tool' => $tool['name'], 'server' => $server->uuid]);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $tools = collect(ToolCatalog::all());
        $categories = ['All', ...$tools->pluck('category')->unique()->sort()->values()->all()];

        return view('livewire.tools.index', [
            'servers' => Server::query()->where('team_id', currentTeam()->id)->get(),
            'categories' => $categories,
            'tools' => $this->category === 'All' ? $tools : $tools->where('category', $this->category),
        ]);
    }
}
