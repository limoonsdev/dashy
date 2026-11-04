<?php

namespace App\Services\Toolkit;

class HardeningScript
{
    /**
     * @var array<string, string>
     */
    public const SECTIONS = [
        'firewall' => 'Firewall (UFW)',
        'fail2ban' => 'Brute-force protection (fail2ban)',
        'crowdsec' => 'CrowdSec behaviour detection',
        'ddos' => 'Kernel and proxy anti-DDoS',
        'wireguard' => 'WireGuard VPN',
        'telemetry' => 'Telemetry blocking',
    ];

    /**
     * Render an auditable script. Nothing here is executed by Dashy: the user
     * reads it, edits the placeholders, and runs it on their own server.
     *
     * @param  array<int, string>  $sections
     */
    public function render(array $sections, string $sshPort = '22'): string
    {
        $sections = array_values(array_intersect($sections, array_keys(self::SECTIONS)));
        $sshPort = preg_match('/^\d{1,5}$/', $sshPort) ? $sshPort : '22';

        $parts = [];
        $parts[] = '#!/usr/bin/env bash';
        $parts[] = '# Dashy hardening script';
        $parts[] = '# Generated for review. Read every section before running it.';
        $parts[] = '# Run as root on the target server: sudo bash dashy-hardening.sh';
        $parts[] = '';
        $parts[] = 'set -euo pipefail';
        $parts[] = '';

        foreach ($sections as $section) {
            $parts[] = match ($section) {
                'firewall' => $this->firewall($sshPort),
                'fail2ban' => $this->fail2ban($sshPort),
                'crowdsec' => $this->crowdsec(),
                'ddos' => $this->ddos(),
                'wireguard' => $this->wireguard(),
                'telemetry' => $this->telemetry(),
            };
        }

        $parts[] = 'echo "Dashy hardening complete."';

        return implode(PHP_EOL, $parts).PHP_EOL;
    }

    private function firewall(string $sshPort): string
    {
        return <<<BASH
# --- Firewall (UFW) -------------------------------------------------------
# Deny inbound by default, allow only what the server actually serves.
# SSH is rate limited so a password-guessing loop is throttled at the kernel
# firewall level, before it reaches sshd.
apt-get update -y
apt-get install -y ufw

ufw default deny incoming
ufw default allow outgoing
ufw limit {$sshPort}/tcp comment 'SSH (rate limited)'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw allow 443/udp comment 'HTTP/3'
ufw --force enable
ufw status verbose

BASH;
    }

    private function fail2ban(string $sshPort): string
    {
        return <<<BASH
# --- Brute-force protection (fail2ban) ------------------------------------
apt-get install -y fail2ban

cat >/etc/fail2ban/jail.d/dashy.local <<'JAIL'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
port    = {$sshPort}
mode    = aggressive
JAIL

systemctl enable --now fail2ban
fail2ban-client status sshd

BASH;
    }

    private function crowdsec(): string
    {
        return <<<'BASH'
# --- CrowdSec -------------------------------------------------------------
# Behaviour detection shared across the CrowdSec network. The firewall
# bouncer turns a detected scan into a local ban automatically.
curl -s https://install.crowdsec.net | sudo sh
apt-get install -y crowdsec crowdsec-firewall-bouncer-iptables
systemctl enable --now crowdsec crowdsec-firewall-bouncer
cscli collections install crowdsecurity/linux crowdsecurity/traefik
cscli decisions list

BASH;
    }

    private function ddos(): string
    {
        return <<<'BASH'
# --- Kernel and proxy anti-DDoS -------------------------------------------
# SYN cookies and a larger accept backlog absorb short floods. These are
# sysctl values, not a replacement for an upstream scrubbing service.
cat >/etc/sysctl.d/99-dashy-hardening.conf <<'SYSCTL'
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_max_syn_backlog = 4096
net.core.somaxconn = 4096
net.ipv4.tcp_fin_timeout = 15
net.ipv4.tcp_keepalive_time = 300
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.all.send_redirects = 0
SYSCTL
sysctl --system

# Traefik already fronts every Dashy app. Add a rate limit middleware in
# Dashy (Proxy > Middlewares) or in the Traefik dynamic config:
#
# http:
#   middlewares:
#     dashy-ratelimit:
#       rateLimit:
#         average: 100
#         burst: 50
#         period: 1s
#
# Attach it to the routers of the public apps that need it.

BASH;
    }

    private function wireguard(): string
    {
        return <<<'BASH'
# --- WireGuard VPN --------------------------------------------------------
# Turns this VPS into a private entry point. Replace SERVER_PUBLIC_IP with the
# address clients connect to, and keep the private key off any backup.
apt-get install -y wireguard qrencode

umask 077
SERVER_PRIVATE_KEY=$(wg genkey)
SERVER_PUBLIC_KEY=$(echo "$SERVER_PRIVATE_KEY" | wg pubkey)

cat >/etc/wireguard/wg0.conf <<WG
[Interface]
Address = 10.66.66.1/24
ListenPort = 51820
PrivateKey = ${SERVER_PRIVATE_KEY}
PostUp   = iptables -A FORWARD -i wg0 -j ACCEPT; iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE
WG

sysctl -w net.ipv4.ip_forward=1
systemctl enable --now wg-quick@wg0

# Client config template (add one [Peer] block per device):
cat <<CLIENT
[Interface]
PrivateKey = <client private key>
Address = 10.66.66.2/32
DNS = 1.1.1.1

[Peer]
PublicKey = ${SERVER_PUBLIC_KEY}
Endpoint = SERVER_PUBLIC_IP:51820
AllowedIPs = 0.0.0.0/0
PersistentKeepalive = 25
CLIENT

wg show

BASH;
    }

    private function telemetry(): string
    {
        return <<<'BASH'
# --- Telemetry blocking ---------------------------------------------------
# Optional: stop common vendor telemetry endpoints at the DNS level. Review
# the list first, because an entry also blocks a feature you may rely on.
cat >>/etc/hosts <<'HOSTS'

# Dashy telemetry block
0.0.0.0 telemetry.mozilla.org
0.0.0.0 incoming.telemetry.mozilla.org
0.0.0.0 settings-win.data.microsoft.com
0.0.0.0 v10.events.data.microsoft.com
0.0.0.0 telemetry.microsoft.com
0.0.0.0 watson.telemetry.microsoft.com
0.0.0.0 app-measurement.com
0.0.0.0 api.segment.io
0.0.0.0 sentry.io
HOSTS

# Docker and the Dashy stack do not send telemetry. This list only covers
# third-party software you install on the same host.

BASH;
    }
}
