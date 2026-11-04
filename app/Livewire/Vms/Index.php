<?php

namespace App\Livewire\Vms;

use App\Services\Vms\IncusClient;
use App\Services\Vms\ProxmoxClient;
use App\Services\Vms\VmCatalog;
use App\Services\Vms\VmProvisioner;
use Livewire\Component;

class Index extends Component
{
    public string $provider = 'proxmox';

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

    /** @var array<string, bool> */
    public array $providers = [];

    public ?string $notice = null;

    public ?string $error = null;

    public bool $wizardOpen = false;

    public int $wizardStep = 1;

    /** @var array<string, mixed> */
    public array $answers = [
        'provider' => 'proxmox',
        'name' => '',
        'os' => 'ubuntu-24.04',
        'cores' => 2,
        'memory' => 2048,
        'disk' => 20,
        'sshKeys' => '',
    ];

    public function mount(): void
    {
        $proxmox = (array) config('vms.proxmox', []);
        $defaults = (array) config('vms.defaults', []);

        $this->provider = (string) config('vms.provider', 'proxmox');
        $this->node = (string) ($proxmox['node'] ?? 'pve');
        $this->storage = (string) ($proxmox['storage'] ?? 'local-lvm');
        $this->bridge = (string) ($proxmox['bridge'] ?? 'vmbr0');
        $this->isoStorage = (string) ($proxmox['iso_storage'] ?? 'local');
        $this->cores = (int) ($defaults['cores'] ?? 2);
        $this->memory = (int) ($defaults['memory'] ?? 2048);
        $this->disk = (int) ($defaults['disk'] ?? 20);
        $this->sshKeys = (string) ($defaults['ssh_keys'] ?? '');
        $this->answers['provider'] = $this->provider;
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $this->providers = [
            'proxmox' => ProxmoxClient::fromConfig()->configured(),
            'incus' => filled(config('vms.incus.url')),
        ];
        $this->configured = (bool) ($this->providers[$this->provider] ?? false);

        if ($this->configured) {
            $this->loadVms();
        }
    }

    public function setProvider(string $provider): void
    {
        if (! array_key_exists($provider, $this->providers)) {
            return;
        }

        $this->provider = $provider;
        $this->answers['provider'] = $provider;
        $this->vms = [];
        $this->error = null;
        $this->refreshStatus();
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
            $this->vms = $this->provider === 'incus' ? $this->incusVms() : $this->proxmoxVms();
        } catch (\Throwable $e) {
            $this->vms = [];
            $this->error = $e->getMessage();
        }
    }

    public function openWizard(): void
    {
        $this->wizardOpen = true;
        $this->wizardStep = 1;
        $this->answers = [
            'provider' => $this->provider,
            'name' => $this->name !== '' ? $this->name : 'dashy-vm-'.random_int(100, 999),
            'os' => $this->os,
            'cores' => $this->cores,
            'memory' => $this->memory,
            'disk' => $this->disk,
            'sshKeys' => $this->sshKeys,
        ];
    }

    public function nextStep(): void
    {
        $this->validateWizardStep();

        $this->wizardStep = min(4, $this->wizardStep + 1);
    }

    public function previousStep(): void
    {
        $this->wizardStep = max(1, $this->wizardStep - 1);
    }

    public function applyWizard(): void
    {
        $this->validateWizardStep();

        $this->provider = (string) $this->answers['provider'];
        $this->name = (string) $this->answers['name'];
        $this->os = (string) $this->answers['os'];
        $this->cores = (int) $this->answers['cores'];
        $this->memory = (int) $this->answers['memory'];
        $this->disk = (int) $this->answers['disk'];
        $this->sshKeys = (string) ($this->answers['sshKeys'] ?? '');
        $this->configured = (bool) ($this->providers[$this->provider] ?? false);

        if (! $this->configured) {
            $this->error = $this->provider === 'incus'
                ? 'Incus is not configured. Set INCUS_URL and INCUS_TOKEN in the environment.'
                : 'Proxmox is not configured. Set PROXMOX_URL and the API token.';
            $this->wizardOpen = false;

            return;
        }

        $this->wizardOpen = false;
        $this->provision();
    }

    public function provision(): void
    {
        $this->validate([
            'provider' => ['required', 'in:proxmox,incus'],
            'name' => ['required', 'string', 'max:63', 'regex:/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/'],
            'cores' => ['required', 'integer', 'between:1,16'],
            'memory' => ['required', 'integer', 'between:256,65536'],
            'disk' => ['required', 'integer', 'between:2,500'],
            'node' => ['required', 'string', 'max:100'],
            'storage' => ['required', 'string', 'max:100'],
            'bridge' => ['required', 'string', 'max:100'],
            'isoStorage' => ['required', 'string', 'max:100'],
            'os' => ['required', 'string', 'max:64'],
            'sshKeys' => ['nullable', 'string', 'max:16384'],
            'password' => ['nullable', 'string', 'max:255'],
            'vmid' => ['nullable', 'string', 'max:64'],
        ]);

        $this->notice = $this->error = null;

        if (! $this->canManage()) {
            $this->error = 'Only a team admin or owner can create virtual machines.';

            return;
        }

        @set_time_limit(0);

        try {
            if ($this->provider === 'incus') {
                $this->provisionIncus();

                return;
            }

            $client = ProxmoxClient::fromConfig();

            if (! $client->configured()) {
                $this->error = 'Proxmox is not configured. Set PROXMOX_URL and PROXMOX_TOKEN_ID/PROXMOX_TOKEN_SECRET.';

                return;
            }

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

    public function start(string|int $id): void
    {
        $this->act($id, 'start');
    }

    public function shutdown(string|int $id): void
    {
        $this->act($id, 'shutdown');
    }

    public function deleteVm(string|int $id): void
    {
        $this->act($id, 'delete');
    }

    public function render()
    {
        return view('livewire.vms.index', [
            'catalog' => VmCatalog::fromConfig()->all(),
            'selected' => VmCatalog::fromConfig()->all()[$this->os] ?? null,
            'providerLabels' => ['proxmox' => 'Proxmox VE', 'incus' => 'Incus / LXD'],
        ]);
    }

    private function provisionIncus(): void
    {
        $client = IncusClient::fromConfig();
        $client->create($this->name, $this->cores, $this->memory, $this->disk);
        $client->start($this->name);

        $this->notice = "Container {$this->name} was created and started on Incus.";
        $this->name = '';
        $this->loadVms();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function proxmoxVms(): array
    {
        return array_map(function (array $vm) {
            return [
                'id' => (int) ($vm['vmid'] ?? 0),
                'name' => (string) ($vm['name'] ?? ''),
                'status' => (string) ($vm['status'] ?? 'unknown'),
                'cpus' => $vm['cpus'] ?? null,
                'memory' => isset($vm['maxmem']) ? (int) $vm['maxmem'] : null,
                'disk' => isset($vm['maxdisk']) ? (int) $vm['maxdisk'] : null,
            ];
        }, ProxmoxClient::fromConfig()->listVms($this->node));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function incusVms(): array
    {
        return array_map(function (array $instance) {
            $config = (array) ($instance['config'] ?? []);
            $memory = (int) preg_replace('/\D/', '', (string) ($config['limits.memory'] ?? '0'));

            return [
                'id' => (string) ($instance['name'] ?? ''),
                'name' => (string) ($instance['name'] ?? ''),
                'status' => (string) ($instance['status'] ?? 'unknown') === 'Running' ? 'running' : 'stopped',
                'cpus' => $config['limits.cpu'] ?? null,
                'memory' => $memory > 0 ? $memory * 1024 * 1024 : null,
                'disk' => null,
            ];
        }, IncusClient::fromConfig()->instances());
    }

    private function act(string|int $id, string $action): void
    {
        $this->notice = $this->error = null;

        if (! $this->canManage()) {
            $this->error = 'Only a team admin or owner can manage virtual machines.';

            return;
        }

        if (! $this->configured) {
            $this->error = 'The selected provider is not configured.';

            return;
        }

        try {
            if ($this->provider === 'incus') {
                $client = IncusClient::fromConfig();
                $name = (string) $id;
                match ($action) {
                    'start' => $client->start($name),
                    'shutdown' => $client->stop($name),
                    'delete' => $client->delete($name),
                };
            } else {
                $client = ProxmoxClient::fromConfig();
                $vmid = (int) $id;
                match ($action) {
                    'start' => $client->startVm($this->node, $vmid),
                    'shutdown' => $client->shutdownVm($this->node, $vmid),
                    'delete' => $client->deleteVm($this->node, $vmid),
                };
            }

            $this->notice = ucfirst($action).' requested for '.$id.'.';
            $this->loadVms();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    private function validateWizardStep(): void
    {
        $rules = match ($this->wizardStep) {
            1 => ['answers.provider' => ['required', 'in:proxmox,incus']],
            2 => [
                'answers.name' => ['required', 'string', 'max:63', 'regex:/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/'],
                'answers.os' => ['required', 'string', 'max:64'],
            ],
            3 => [
                'answers.cores' => ['required', 'integer', 'between:1,16'],
                'answers.memory' => ['required', 'integer', 'between:256,65536'],
                'answers.disk' => ['required', 'integer', 'between:2,500'],
            ],
            default => ['answers.sshKeys' => ['nullable', 'string', 'max:16384']],
        };

        $this->validate($rules);
    }

    private function canManage(): bool
    {
        $user = auth()->user();
        $team = currentTeam();

        return $user !== null && $team !== null && $user->isAdminOfTeam($team->id);
    }
}
