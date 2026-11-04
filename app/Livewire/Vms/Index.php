<?php

namespace App\Livewire\Vms;

use App\Services\Vms\ProxmoxClient;
use App\Services\Vms\VmCatalog;
use App\Services\Vms\VmProvisioner;
use Livewire\Component;

class Index extends Component
{
    public string $os = 'ubuntu-24.04';

    public string $name = '';

    public string $node = 'pve';

    public string $storage = 'local-lvm';

    public string $bridge = 'vmbr0';

    public string $isoStorage = 'local';

    public string $iso = '';

    public string $imageUrl = '';

    public string $sshKeys = '';

    public string $password = '';

    public string $vmid = '';

    public int $cores = 2;

    public int $memory = 2048;

    public int $disk = 20;

    /** @var array<int, array<string, mixed>> */
    public array $vms = [];

    public bool $configured = false;

    public ?string $notice = null;

    public ?string $error = null;

    public function mount(): void
    {
        $proxmox = (array) config('vms.proxmox', []);
        $defaults = (array) config('vms.defaults', []);

        $this->node = (string) ($proxmox['node'] ?? 'pve');
        $this->storage = (string) ($proxmox['storage'] ?? 'local-lvm');
        $this->bridge = (string) ($proxmox['bridge'] ?? 'vmbr0');
        $this->isoStorage = (string) ($proxmox['iso_storage'] ?? 'local');
        $this->cores = (int) ($defaults['cores'] ?? 2);
        $this->memory = (int) ($defaults['memory'] ?? 2048);
        $this->disk = (int) ($defaults['disk'] ?? 20);
        $this->sshKeys = (string) ($defaults['ssh_keys'] ?? '');
        $this->configured = ProxmoxClient::fromConfig()->configured();

        if ($this->configured) {
            $this->loadVms();
        }
    }

    public function updatedOs(): void
    {
        $os = VmCatalog::fromConfig()->all()[$this->os] ?? null;

        if (! is_array($os)) {
            return;
        }

        $this->memory = max($this->memory, (int) ($os['memory'] ?? 2048));
        $this->disk = max($this->disk, (int) ($os['disk'] ?? 20));
    }

    public function loadVms(): void
    {
        $this->error = null;

        if (! $this->configured) {
            return;
        }

        try {
            $this->vms = ProxmoxClient::fromConfig()->listVms($this->node);
        } catch (\Throwable $e) {
            $this->vms = [];
            $this->error = $e->getMessage();
        }
    }

    public function provision(): void
    {
        $this->validate([
            'os' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:63', 'regex:/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/'],
            'node' => ['required', 'string', 'max:100'],
            'storage' => ['required', 'string', 'max:100'],
            'bridge' => ['required', 'string', 'max:100'],
            'isoStorage' => ['required', 'string', 'max:100'],
            'cores' => ['required', 'integer', 'between:1,16'],
            'memory' => ['required', 'integer', 'between:256,65536'],
            'disk' => ['required', 'integer', 'between:2,500'],
            'iso' => ['nullable', 'string', 'max:255'],
            'imageUrl' => ['nullable', 'string', 'max:2048'],
            'sshKeys' => ['nullable', 'string', 'max:16384'],
            'password' => ['nullable', 'string', 'max:255'],
            'vmid' => ['nullable', 'string', 'max:9'],
        ]);

        $this->notice = $this->error = null;

        if (! $this->canManage()) {
            $this->error = 'Only a team admin or owner can create virtual machines.';

            return;
        }

        $client = ProxmoxClient::fromConfig();

        if (! $client->configured()) {
            $this->error = 'Proxmox is not configured. Set PROXMOX_URL and PROXMOX_TOKEN_ID/PROXMOX_TOKEN_SECRET.';

            return;
        }

        // Image download and import can take several minutes; the request must
        // outlive the default PHP execution limit.
        @set_time_limit(0);

        try {
            $result = (new VmProvisioner($client, VmCatalog::fromConfig(), (array) config('vms.limits', [])))
                ->provision([
                    'os' => $this->os,
                    'name' => $this->name,
                    'node' => $this->node,
                    'storage' => $this->storage,
                    'iso_storage' => $this->isoStorage,
                    'bridge' => $this->bridge,
                    'cores' => $this->cores,
                    'memory' => $this->memory,
                    'disk' => $this->disk,
                    'vmid' => $this->vmid,
                    'iso' => $this->iso,
                    'image_url' => $this->imageUrl,
                    'ssh_keys' => trim($this->sshKeys),
                    'password' => $this->password,
                ]);

            $this->notice = "VM {$result['vmid']} ({$result['name']}) was created and started on {$result['node']}.";
            $this->name = '';
            $this->vmid = '';
            $this->password = '';
            $this->loadVms();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function start(int $vmid): void
    {
        $this->act(fn (ProxmoxClient $client) => $client->startVm($this->node, $vmid), "VM {$vmid} is starting.");
    }

    public function shutdown(int $vmid): void
    {
        $this->act(fn (ProxmoxClient $client) => $client->shutdownVm($this->node, $vmid), "Shutdown requested for VM {$vmid}.");
    }

    public function deleteVm(int $vmid): void
    {
        $this->act(fn (ProxmoxClient $client) => $client->deleteVm($this->node, $vmid), "Deletion requested for VM {$vmid}.");
    }

    public function render()
    {
        $catalog = VmCatalog::fromConfig()->all();

        return view('livewire.vms.index', [
            'catalog' => $catalog,
            'selected' => $catalog[$this->os] ?? null,
        ]);
    }

    private function act(callable $action, string $message): void
    {
        $this->notice = $this->error = null;

        if (! $this->canManage()) {
            $this->error = 'Only a team admin or owner can manage virtual machines.';

            return;
        }

        $client = ProxmoxClient::fromConfig();

        if (! $client->configured()) {
            $this->error = 'Proxmox is not configured.';

            return;
        }

        try {
            $action($client);
            $this->notice = $message;
            $this->loadVms();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    private function canManage(): bool
    {
        $user = auth()->user();
        $team = currentTeam();

        return $user !== null && $team !== null && $user->isAdminOfTeam($team->id);
    }
}
