<?php

namespace App\Services\Vms;

use InvalidArgumentException;

class VmProvisioner
{
    public function __construct(
        private ProxmoxClient $client,
        private ?VmCatalog $catalog = null,
        private array $limits = [],
    ) {
        $this->catalog ??= new VmCatalog;
        $this->limits = array_merge([
            'max_cores' => 16,
            'max_memory' => 65536,
            'max_disk' => 500,
        ], $this->limits);
    }

    /**
     * Create a VM from the catalog entry in $input['os'].
     *
     * Expected input: os, node, name, storage, bridge, iso_storage, and
     * optionally vmid, cores, memory, disk, ssh_keys, password, iso, image_url.
     *
     * @param  array<string, mixed>  $input
     * @return array{vmid: int, name: string, node: string, os: string, kind: string}
     */
    public function provision(array $input): array
    {
        $osKey = (string) ($input['os'] ?? '');
        $os = $this->catalog->get($osKey);
        $kind = (string) ($os['kind'] ?? 'cloud');

        $node = $this->identifier($input['node'] ?? '', 'node');
        $storage = $this->identifier($input['storage'] ?? '', 'storage');
        $bridge = $this->identifier($input['bridge'] ?? '', 'bridge');
        $isoStorage = $this->identifier($input['iso_storage'] ?? '', 'iso storage');

        $vmid = isset($input['vmid']) && $input['vmid'] !== '' ? (int) $input['vmid'] : $this->client->nextVmid();
        $this->assertVmid($vmid);

        $name = $this->name($input['name'] ?? $osKey.'-'.$vmid);
        $cores = $this->bounded($input['cores'] ?? 2, 1, (int) ($this->limits['max_cores'] ?? 16), 'cores');
        $memory = $this->bounded($input['memory'] ?? ($os['memory'] ?? 2048), 256, (int) ($this->limits['max_memory'] ?? 65536), 'memory');
        $disk = $this->bounded($input['disk'] ?? ($os['disk'] ?? 20), 2, (int) ($this->limits['max_disk'] ?? 500), 'disk');

        if ($kind === 'template') {
            return $this->provisionTemplate($node, $vmid, $name, $cores, $memory, $os);
        }

        if ($kind === 'iso') {
            return $this->provisionIso($node, $vmid, $name, $cores, $memory, $disk, $storage, $bridge, $os, $input);
        }

        return $this->provisionCloud($node, $vmid, $name, $cores, $memory, $disk, $storage, $isoStorage, $bridge, $os, $input);
    }

    /**
     * @param  array<string, mixed>  $os
     * @param  array<string, mixed>  $input
     * @return array{vmid: int, name: string, node: string, os: string, kind: string}
     */
    private function provisionCloud(
        string $node,
        int $vmid,
        string $name,
        int $cores,
        int $memory,
        int $disk,
        string $storage,
        string $isoStorage,
        string $bridge,
        array $os,
        array $input,
    ): array {
        $url = (string) ($input['image_url'] ?? $os['url'] ?? '');

        if (! $this->validImageUrl($url)) {
            throw new InvalidArgumentException('A valid HTTPS cloud image URL is required.');
        }

        $filename = 'dashy-'.$vmid.'-'.preg_replace('/[^a-z0-9.-]/i', '-', (string) ($input['os'] ?? 'image')).'.img';

        $download = $this->client->downloadUrl($node, $isoStorage, $url, $filename, $os['checksum'] ?? null);
        $this->client->waitForTask($node, $download);

        $params = [
            'vmid' => $vmid,
            'name' => $name,
            'cores' => $cores,
            'memory' => $memory,
            'ostype' => (string) ($os['ostype'] ?? 'l26'),
            'scsihw' => 'virtio-scsi-pci',
            'scsi0' => "{$storage}:0,import-from={$isoStorage}:iso/{$filename}",
            'ide2' => "{$storage}:cloudinit",
            'net0' => "virtio,bridge={$bridge}",
            'serial0' => 'socket',
            'vga' => 'serial0',
            'agent' => 1,
            'boot' => 'order=scsi0',
            'ciuser' => (string) ($os['user'] ?? 'dashy'),
            'ipconfig0' => 'ip=dhcp',
        ];

        $sshKeys = $input['ssh_keys'] ?? null;

        if (is_string($sshKeys) && trim($sshKeys) !== '') {
            $params['sshkeys'] = $sshKeys;
        }

        $password = $input['password'] ?? null;

        if (is_string($password) && trim($password) !== '') {
            $params['cipassword'] = $password;
        }

        $task = $this->client->createVm($node, $params);
        $this->client->waitForTask($node, $task);

        if ($disk > (int) ($os['disk'] ?? 0)) {
            $resize = $this->client->resizeDisk($node, $vmid, 'scsi0', $disk);
            $this->client->waitForTask($node, $resize);
        }

        $this->client->startVm($node, $vmid);

        return $this->summary($vmid, $name, $node, $os, 'cloud');
    }

    /**
     * @param  array<string, mixed>  $os
     * @param  array<string, mixed>  $input
     * @return array{vmid: int, name: string, node: string, os: string, kind: string}
     */
    private function provisionIso(
        string $node,
        int $vmid,
        string $name,
        int $cores,
        int $memory,
        int $disk,
        string $storage,
        string $bridge,
        array $os,
        array $input,
    ): array {
        $iso = (string) ($input['iso'] ?? $os['iso'] ?? '');

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*:iso\/[^\/]+\.(iso|img)$/i', $iso)) {
            throw new InvalidArgumentException('An ISO volume such as local:iso/windows.iso is required.');
        }

        $params = [
            'vmid' => $vmid,
            'name' => $name,
            'cores' => $cores,
            'memory' => $memory,
            'ostype' => (string) ($os['ostype'] ?? 'other'),
            'scsihw' => 'virtio-scsi-pci',
            'scsi0' => "{$storage}:{$disk}",
            'ide2' => "{$iso},media=cdrom",
            'net0' => "virtio,bridge={$bridge}",
            'boot' => 'order=ide2;scsi0',
            'agent' => 1,
        ];

        $task = $this->client->createVm($node, $params);
        $this->client->waitForTask($node, $task);

        $this->client->startVm($node, $vmid);

        return $this->summary($vmid, $name, $node, $os, 'iso');
    }

    /**
     * @param  array<string, mixed>  $os
     * @return array{vmid: int, name: string, node: string, os: string, kind: string}
     */
    private function provisionTemplate(string $node, int $vmid, string $name, int $cores, int $memory, array $os): array
    {
        $template = (int) ($os['template'] ?? 0);

        if ($template <= 0) {
            throw new InvalidArgumentException('This catalog entry has no template id.');
        }

        $task = $this->client->cloneVm($node, $template, [
            'newid' => $vmid,
            'name' => $name,
            'full' => 1,
        ]);
        $this->client->waitForTask($node, $task);

        $this->client->startVm($node, $vmid);

        return $this->summary($vmid, $name, $node, $os, 'template');
    }

    /**
     * @param  array<string, mixed>  $os
     * @return array{vmid: int, name: string, node: string, os: string, kind: string}
     */
    private function summary(int $vmid, string $name, string $node, array $os, string $kind): array
    {
        return [
            'vmid' => $vmid,
            'name' => $name,
            'node' => $node,
            'os' => (string) ($os['label'] ?? $kind),
            'kind' => $kind,
        ];
    }

    private function identifier(mixed $value, string $label): string
    {
        $value = (string) $value;

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value)) {
            throw new InvalidArgumentException("Invalid {$label}.");
        }

        return $value;
    }

    private function name(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/', $value)) {
            throw new InvalidArgumentException('VM names may only contain letters, digits and hyphens (max 63 characters).');
        }

        return $value;
    }

    private function assertVmid(int $vmid): void
    {
        if ($vmid < 100 || $vmid > 999999999) {
            throw new InvalidArgumentException('VM IDs must be between 100 and 999999999.');
        }
    }

    private function bounded(mixed $value, int $min, int $max, string $label): int
    {
        $value = (int) $value;

        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("Invalid {$label}: expected {$min}-{$max}.");
        }

        return $value;
    }

    private function validImageUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return $scheme === 'https' && preg_match('/\.(img|qcow2|raw)$/', $path) === 1;
    }
}
