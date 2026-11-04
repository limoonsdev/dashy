<?php

namespace App\Services\Toolkit;

/**
 * Generates the guest-side setup that a VM needs to behave like a first-class
 * Dashy host: the QEMU guest agent, virtio drivers, an I/O scheduler that suits
 * a virtual disk, memory pressure tuning and discard/TRIM support.
 *
 * This is the DashyVMToolkit analogue of VMware Tools. Dashy never runs it.
 */
class VmToolkitScript
{
    /**
     * @var array<string, string>
     */
    public const OPTIONS = [
        'guest_agent' => 'QEMU guest agent (IP reports, graceful shutdown from Dashy)',
        'virtio' => 'VirtIO drivers and balanced I/O scheduler',
        'tuning' => 'Memory and network tuning (zram, swappiness, BBR)',
        'trim' => 'Discard / TRIM timer (thin-provisioned disks stay small)',
        'serial' => 'Serial console (recovery when the network does not come up)',
        'time' => 'chrony time sync (clocks drift under virtualisation)',
    ];

    /**
     * @var array<string, string>
     */
    public const FAMILIES = [
        'debian' => 'Debian / Ubuntu',
        'rhel' => 'RHEL / Rocky / AlmaLinux / Fedora',
        'suse' => 'openSUSE / SLES',
        'alpine' => 'Alpine Linux',
        'arch' => 'Arch Linux',
    ];

    /**
     * @param  array<int, string>  $options
     */
    public function render(array $options, string $family = 'debian'): string
    {
        $options = array_values(array_intersect($options, array_keys(self::OPTIONS)));
        $family = isset(self::FAMILIES[$family]) ? $family : 'debian';

        if ($options === []) {
            return '';
        }

        $parts = [
            '#!/usr/bin/env bash',
            '# DashyVMToolkit — guest setup for a virtual machine managed by Dashy',
            '# Generated for '.self::FAMILIES[$family].'. Read it before running: it installs packages.',
            '# Run as root inside the VM: sudo bash dashy-vm-toolkit.sh',
            '',
            'set -euo pipefail',
            '',
            $this->packages($family, $options),
        ];

        if (in_array('virtio', $options, true)) {
            $parts[] = $this->virtio($family);
        }
        if (in_array('tuning', $options, true)) {
            $parts[] = $this->tuning();
        }
        if (in_array('trim', $options, true)) {
            $parts[] = $this->trim($family);
        }
        if (in_array('serial', $options, true)) {
            $parts[] = $this->serial($family);
        }
        if (in_array('time', $options, true)) {
            $parts[] = $this->time($family);
        }

        $parts[] = 'echo "DashyVMToolkit applied. Reboot to pick up kernel parameters."';

        return implode(PHP_EOL, $parts).PHP_EOL;
    }

    /**
     * @param  array<int, string>  $options
     */
    private function packages(string $family, array $options): string
    {
        $packages = [];

        if (in_array('guest_agent', $options, true)) {
            $packages[] = 'qemu-guest-agent';
        }
        if (in_array('time', $options, true)) {
            $packages[] = $family === 'suse' ? 'chrony' : 'chrony';
        }
        if (in_array('trim', $options, true) && $family === 'debian') {
            $packages[] = 'util-linux';
        }

        if ($packages === []) {
            return "# No packages to install for this selection.\n";
        }

        $list = implode(' ', array_unique($packages));

        return match ($family) {
            'debian' => "export DEBIAN_FRONTEND=noninteractive\napt-get update -y\napt-get install -y {$list}\n",
            'rhel' => "dnf install -y {$list}\n",
            'suse' => "zypper --non-interactive install {$list}\n",
            'alpine' => "apk add --no-cache {$this->alpinePackages($options)}\n",
            'arch' => "pacman -Sy --noconfirm {$list}\n",
        };
    }

    /**
     * @param  array<int, string>  $options
     */
    private function alpinePackages(array $options): string
    {
        $packages = [];

        if (in_array('guest_agent', $options, true)) {
            $packages[] = 'qemu-guest-agent';
        }
        if (in_array('time', $options, true)) {
            $packages[] = 'chrony';
        }

        return implode(' ', $packages);
    }

    private function virtio(string $family): string
    {
        $enable = match ($family) {
            'debian' => "systemctl enable --now qemu-guest-agent",
            'rhel', 'suse', 'arch' => "systemctl enable --now qemu-guest-agent",
            'alpine' => "rc-update add qemu-guest-agent default && rc-service qemu-guest-agent start",
        };

        return <<<BASH

# --- VirtIO ---------------------------------------------------------------
# The guest agent lets Dashy report the VM IP and shut it down cleanly.
{$enable}

# A virtual disk is not a spinning rust device: none is the right scheduler and
# it removes a per-request queue that only adds latency.
for disk in /sys/block/vd*/queue/scheduler; do
    [ -w "\$disk" ] && echo none > "\$disk"
done

# Keep the scheduler after a reboot without touching the initramfs.
cat >/etc/udev/rules.d/60-dashy-virtio.rules <<'UDEV'
ACTION=="add|change", KERNEL=="vd*", ATTR{queue/scheduler}="none", ATTR{queue/rotational}="0"
UDEV

BASH;
    }

    private function tuning(): string
    {
        return <<<'BASH'
# --- Memory and network tuning --------------------------------------------
# zram turns spare CPU into compressed swap in RAM: cheaper than a swap file
# on a thin-provisioned virtual disk, and it keeps the host from swapping.
cat >/etc/sysctl.d/99-dashy-vm.conf <<'SYSCTL'
vm.swappiness = 100
vm.vfs_cache_pressure = 50
vm.dirty_ratio = 10
vm.dirty_background_ratio = 5
net.core.default_qdisc = fq
net.ipv4.tcp_congestion_control = bbr
net.ipv4.tcp_slow_start_after_idle = 0
SYSCTL
sysctl --system

BASH;
    }

    private function trim(string $family): string
    {
        $enable = match ($family) {
            'debian', 'rhel', 'suse', 'arch' => "systemctl enable --now fstrim.timer",
            'alpine' => "rc-update add fstrim default || true",
        };

        return <<<BASH

# --- Discard / TRIM -------------------------------------------------------
# Without this the qcow2 grows forever even after files are deleted.
{$enable}

BASH;
    }

    private function serial(string $family): string
    {
        $console = match ($family) {
            'debian', 'arch' => 'console=tty0 console=ttyS0,115200n8',
            'rhel', 'suse', 'alpine' => 'console=ttyS0,115200n8 console=tty0',
        };

        return <<<BASH

# --- Serial console -------------------------------------------------------
# Recovery path: Dashy can attach to ttyS0 even when sshd never starts.
if [ -d /etc/default/grub ] && grep -q '^GRUB_CMDLINE_LINUX=' /etc/default/grub; then
    sed -i 's/^GRUB_CMDLINE_LINUX="\\(.*\\)"/GRUB_CMDLINE_LINUX="\\1 {$console}"/' /etc/default/grub
    (command -v update-grub >/dev/null && update-grub) || (command -v grub2-mkconfig >/dev/null && grub2-mkconfig -o /boot/grub2/grub.cfg) || true
fi

BASH;
    }

    private function time(string $family): string
    {
        $enable = match ($family) {
            'debian', 'rhel', 'suse', 'arch' => "systemctl enable --now chrony",
            'alpine' => "rc-update add chronyd default && rc-service chronyd start",
        };

        return <<<BASH

# --- Time sync ------------------------------------------------------------
# A VM clock drifts when the host pauses it; a wrong clock breaks TLS and logs.
{$enable}

BASH;
    }
}
