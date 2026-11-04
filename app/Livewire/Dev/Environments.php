<?php

namespace App\Livewire\Dev;

use App\Models\Server;
use Livewire\Component;

/**
 * Generates a ready-to-run container command for a browser IDE or a local AI
 * studio, so both can be started on any server without a compose template.
 */
class Environments extends Component
{
    public string $serverUuid = '';

    public string $environment = 'code-server';

    public int $port = 8443;

    public string $password = '';

    public int $memory = 2048;

    public function mount(): void
    {
        $this->serverUuid = (string) (Server::query()->where('team_id', currentTeam()->id)->value('uuid') ?? '');
    }

    public function updatedEnvironment(string $environment): void
    {
        $this->port = $environment === 'ai-studio' ? 3000 : 8443;
    }

    public function setEnvironment(string $environment): void
    {
        if (! in_array($environment, ['code-server', 'ai-studio'], true)) {
            return;
        }

        $this->environment = $environment;
        $this->port = $environment === 'ai-studio' ? 3000 : 8443;
    }

    public function render()
    {
        return view('livewire.dev.environments', [
            'servers' => Server::query()->where('team_id', currentTeam()->id)->get(),
            'command' => $this->command(),
            'environments' => [
                'code-server' => ['label' => 'Code Server', 'description' => 'VS Code in the browser, with a persistent workspace volume.'],
                'ai-studio' => ['label' => 'AI Studio', 'description' => 'Open WebUI chat front end, with Ollama for local models.'],
            ],
        ]);
    }

    public function command(): string
    {
        $secret = $this->password !== '' ? $this->password : 'change-me-'.substr(sha1((string) $this->serverUuid), 0, 8);
        $name = $this->environment === 'ai-studio' ? 'dashy-ai-studio' : 'dashy-code-server';
        $memory = max(512, min(32768, $this->memory));
        $port = max(1, min(65535, $this->port));

        if ($this->environment === 'ai-studio') {
            return implode("\n", [
                'docker run -d --name '.$name.' --restart unless-stopped \\',
                '  --memory '.$memory.'m \\',
                '  -p '.$port.':8080 \\',
                '  -v dashy_ai_data:/app/backend/data \\',
                '  -e WEBUI_AUTH=true \\',
                '  -e OLLAMA_BASE_URL=http://host.docker.internal:11434 \\',
                '  ghcr.io/open-webui/open-webui:main',
            ]);
        }

        return implode("\n", [
            'docker run -d --name '.$name.' --restart unless-stopped \\',
            '  --memory '.$memory.'m \\',
            '  -p '.$port.':8443 \\',
            '  -e PUID=1000 -e PGID=1000 \\',
            '  -e TZ=Etc/UTC \\',
            '  -e PASSWORD='.escapeshellarg($secret).' \\',
            '  -e SUDO_PASSWORD='.escapeshellarg($secret).' \\',
            '  -v dashy_code_workspace:/config/workspace \\',
            '  lscr.io/linuxserver/code-server:latest',
        ]);
    }
}
