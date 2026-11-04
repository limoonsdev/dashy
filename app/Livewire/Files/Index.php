<?php

namespace App\Livewire\Files;

use App\Models\Server;
use App\Services\ServerFileManager;
use Livewire\Component;

class Index extends Component
{
    public string $serverUuid = '';

    public string $path = '/';

    /** @var array<int, array<string, mixed>> */
    public array $entries = [];

    public ?string $editingPath = null;

    public string $content = '';

    public string $newName = '';

    public string $chmod = '';

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(): void
    {
        $server = $this->servers()->first();

        if ($server !== null) {
            $this->serverUuid = $server->uuid;
            $this->path = (new ServerFileManager($server))->defaultRoot();
            $this->load();
        }
    }

    public function updatedServerUuid(): void
    {
        $this->editingPath = null;
        $this->content = '';
        $this->path = $this->server()->isLocalhost() ? '/data/dashy' : '/';
        $this->load();
    }

    public function go(): void
    {
        $this->load();
    }

    public function up(): void
    {
        $this->path = dirname($this->path) ?: '/';
        $this->load();
    }

    public function open(string $target): void
    {
        $this->error = $this->notice = null;

        try {
            $entry = collect($this->entries)->firstWhere('path', $target);

            if (($entry['type'] ?? 'file') === 'dir') {
                $this->path = $target;
                $this->load();

                return;
            }

            $manager = $this->manager();
            $this->content = $manager->read($target);
            $this->editingPath = $target;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function save(): void
    {
        if ($this->editingPath === null) {
            return;
        }

        try {
            $this->manager()->write($this->editingPath, $this->content);
            $this->notice = 'Saved '.$this->editingPath.'.';
            $this->load();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function create(): void
    {
        $this->validate(['newName' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/']]);
        $target = rtrim($this->path, '/').'/'.$this->newName;

        try {
            $this->manager()->createFile($target);
            $this->newName = '';
            $this->notice = 'Created '.$target.'.';
            $this->load();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function makeDirectory(): void
    {
        $this->validate(['newName' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/']]);
        $target = rtrim($this->path, '/').'/'.$this->newName;

        try {
            $this->manager()->makeDirectory($target);
            $this->newName = '';
            $this->notice = 'Created directory '.$target.'.';
            $this->load();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function remove(string $target): void
    {
        try {
            $this->manager()->delete($target);
            $this->notice = 'Deleted '.$target.'.';

            if ($this->editingPath === $target) {
                $this->editingPath = null;
                $this->content = '';
            }

            $this->load();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function applyChmod(string $target): void
    {
        try {
            $this->manager()->chmod($target, $this->chmod);
            $this->notice = 'Permissions updated.';
            $this->load();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function compress(string $target): void
    {
        try {
            $this->manager()->compress($target);
            $this->notice = 'Archive created next to '.basename($target).'.';
            $this->load();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function load(): void
    {
        $this->error = null;

        try {
            $this->entries = $this->manager()->list($this->path);
        } catch (\Throwable $e) {
            $this->entries = [];
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.files.index', [
            'servers' => $this->servers()->get(),
        ]);
    }

    private function servers()
    {
        return Server::query()->where('team_id', currentTeam()->id);
    }

    private function server(): Server
    {
        return $this->servers()->where('uuid', $this->serverUuid)->firstOrFail();
    }

    private function manager(): ServerFileManager
    {
        return new ServerFileManager($this->server());
    }
}
