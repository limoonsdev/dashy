<?php

namespace App\Services\Vms;

use InvalidArgumentException;

/**
 * OS catalog for VM provisioning.
 *
 * Three kinds are supported:
 * - cloud:    a qcow2/raw cloud image imported as the boot disk, with cloud-init
 *             injecting the user, SSH keys and DHCP networking.
 * - iso:      an installer ISO already present in Proxmox ISO storage (Windows,
 *             FreeBSD, or any distribution without a cloud image).
 * - template: an existing Proxmox template cloned into a full VM.
 *
 * "Any OS" is covered by the custom entry: pass any HTTPS cloud image URL or
 * any ISO volume, so the catalog never has to ship every distribution.
 */
class VmCatalog
{
    /**
     * @param  array<string, array<string, mixed>>|null  $entries
     */
    public function __construct(private ?array $entries = null)
    {
        $this->entries ??= self::defaults();
    }

    public static function fromConfig(): self
    {
        $entries = config('vms.catalog');

        return new self(is_array($entries) && $entries !== [] ? $entries : self::defaults());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        if (! isset($this->entries[$key])) {
            throw new InvalidArgumentException("Unknown OS [{$key}].");
        }

        return $this->entries[$key];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'ubuntu-24.04' => [
                'label' => 'Ubuntu 24.04 LTS',
                'kind' => 'cloud',
                'url' => 'https://cloud-images.ubuntu.com/noble/current/noble-server-cloudimg-amd64.img',
                'user' => 'ubuntu',
                'disk' => 10,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
            'ubuntu-22.04' => [
                'label' => 'Ubuntu 22.04 LTS',
                'kind' => 'cloud',
                'url' => 'https://cloud-images.ubuntu.com/jammy/current/jammy-server-cloudimg-amd64.img',
                'user' => 'ubuntu',
                'disk' => 10,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
            'debian-13' => [
                'label' => 'Debian 13',
                'kind' => 'cloud',
                'url' => 'https://cloud.debian.org/images/cloud/trixie/latest/debian-13-genericcloud-amd64.qcow2',
                'user' => 'debian',
                'disk' => 10,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
            'debian-12' => [
                'label' => 'Debian 12',
                'kind' => 'cloud',
                'url' => 'https://cloud.debian.org/images/cloud/bookworm/latest/debian-12-genericcloud-amd64.qcow2',
                'user' => 'debian',
                'disk' => 10,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
            'almalinux-9' => [
                'label' => 'AlmaLinux 9',
                'kind' => 'cloud',
                'url' => 'https://repo.almalinux.org/almalinux/9/cloud/x86_64/images/AlmaLinux-9-GenericCloud-latest.x86_64.qcow2',
                'user' => 'almalinux',
                'disk' => 10,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
            'rocky-9' => [
                'label' => 'Rocky Linux 9',
                'kind' => 'cloud',
                'url' => 'https://download.rocky-linux.org/9/images/x86_64/Rocky-9-GenericCloud-Base.latest.x86_64.qcow2',
                'user' => 'rocky',
                'disk' => 10,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
            'opensuse-leap-15.6' => [
                'label' => 'openSUSE Leap 15.6',
                'kind' => 'cloud',
                'url' => 'https://download.opensuse.org/distribution/leap/15.6/appliances/openSUSE-Leap-15.6-JeOS.x86_64-OpenStack-Cloud.qcow2',
                'user' => 'opensuse',
                'disk' => 10,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
            'alpine-3.20' => [
                'label' => 'Alpine Linux 3.20',
                'kind' => 'cloud',
                'url' => 'https://dl-cdn.alpinelinux.org/alpine/v3.20/releases/cloud/nocloud_alpine-3.20.3-x86_64-bios-cloudinit-r0.qcow2',
                'user' => 'alpine',
                'disk' => 2,
                'memory' => 512,
                'ostype' => 'l26',
            ],
            'arch' => [
                'label' => 'Arch Linux',
                'kind' => 'cloud',
                'url' => 'https://geo.mirror.pkgbuild.com/images/latest/Arch-Linux-x86_64-cloudimg.qcow2',
                'user' => 'arch',
                'disk' => 10,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
            'windows-2022' => [
                'label' => 'Windows Server 2022',
                'kind' => 'iso',
                'iso' => null,
                'disk' => 40,
                'memory' => 4096,
                'ostype' => 'win11',
                'note' => 'Attach your own Windows ISO and VirtIO drivers ISO. Unattended install needs an answer file.',
            ],
            'windows-11' => [
                'label' => 'Windows 11',
                'kind' => 'iso',
                'iso' => null,
                'disk' => 64,
                'memory' => 8192,
                'ostype' => 'win11',
                'note' => 'Attach your own Windows ISO and VirtIO drivers ISO, then complete Setup over the console.',
            ],
            'freebsd-14' => [
                'label' => 'FreeBSD 14',
                'kind' => 'iso',
                'iso' => null,
                'disk' => 20,
                'memory' => 2048,
                'ostype' => 'other',
                'note' => 'Attach the FreeBSD installer ISO. cloud-init is not used.',
            ],
            'custom-cloud' => [
                'label' => 'Custom cloud image (URL)',
                'kind' => 'custom',
                'disk' => 10,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
            'custom-iso' => [
                'label' => 'Custom ISO / template',
                'kind' => 'iso',
                'iso' => null,
                'disk' => 20,
                'memory' => 2048,
                'ostype' => 'l26',
            ],
        ];
    }
}
