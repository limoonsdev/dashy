<?php

namespace App\Services\Toolkit;

/**
 * Command generator for the security tools people reach for on a pentest box.
 *
 * Dashy never runs these: it renders an auditable script with the values the
 * user typed, so a wrong target is visible before anything executes.
 */
class SecurityToolkit
{
    /**
     * @var array<string, array{label: string, category: string, description: string, needs: array<int, string>, command: string}>
     */
    public const TOOLS = [
        'john' => [
            'label' => 'John the Ripper',
            'category' => 'Passwords',
            'description' => 'Offline password cracking. Detects the hash format automatically, then shows the recovered passwords.',
            'needs' => ['hashfile'],
            'command' => <<<'BASH'
# John the Ripper: install, crack, then print what it recovered.
apt-get install -y john
john --wordlist=/usr/share/wordlists/rockyou.txt __HASHFILE__
john --show __HASHFILE__
BASH,
        ],
        'hashcat' => [
            'label' => 'hashcat',
            'category' => 'Passwords',
            'description' => 'GPU cracking. Mode 0 is MD5, 1000 is NTLM, 1800 is sha512crypt; `hashcat -h` lists them all.',
            'needs' => ['hashfile', 'wordlist'],
            'command' => <<<'BASH'
# hashcat: -m is the hash mode, -a 0 a straight dictionary attack.
apt-get install -y hashcat
hashcat -m __HASHMODE__ -a 0 __HASHFILE__ __WORDLIST__ --force
hashcat -m __HASHMODE__ --show __HASHFILE__
BASH,
        ],
        'nmap' => [
            'label' => 'Nmap',
            'category' => 'Network',
            'description' => 'Service and version discovery. Scan only hosts you are allowed to scan.',
            'needs' => ['target'],
            'command' => <<<'BASH'
# Nmap: every TCP port, service banners, default scripts.
apt-get install -y nmap
nmap -sV -sC -p- -T4 __TARGET__ -oN nmap-__TARGETSAFE__.txt
BASH,
        ],
        'hydra' => [
            'label' => 'Hydra',
            'category' => 'Network',
            'description' => 'Online credential testing against SSH. Four parallel tasks keeps it from locking accounts in a lab.',
            'needs' => ['target', 'userlist', 'wordlist'],
            'command' => <<<'BASH'
# Hydra: only against systems you own or are contracted to test.
apt-get install -y hydra
hydra -L __USERLIST__ -P __WORDLIST__ -t 4 -f ssh://__TARGET__
BASH,
        ],
        'lynis' => [
            'label' => 'Lynis',
            'category' => 'Audit',
            'description' => 'Local hardening audit with a score and concrete next steps.',
            'needs' => [],
            'command' => <<<'BASH'
# Lynis: read the suggestions, do not apply them blind.
apt-get install -y lynis
lynis audit system
BASH,
        ],
        'clamav' => [
            'label' => 'ClamAV',
            'category' => 'Malware',
            'description' => 'On-demand malware scan of a directory tree, printing only infected files.',
            'needs' => ['path'],
            'command' => <<<'BASH'
# ClamAV: the signature database is large, the first update takes a few minutes.
apt-get install -y clamav
systemctl stop clamav-freshclam 2>/dev/null || true
freshclam || true
clamscan -r --bell -i __PATH__ | tee clamav-__TARGETSAFE__.log
BASH,
        ],
        'ssh-audit' => [
            'label' => 'SSH audit',
            'category' => 'Audit',
            'description' => 'Checks the algorithms your sshd actually offers, which matters more than the config file.',
            'needs' => ['target'],
            'command' => <<<'BASH'
# Effective sshd configuration and the algorithms offered on the wire.
sshd -T | grep -Ei 'permitrootlogin|passwordauthentication|kexalgorithms|ciphers|macs'
ssh -o BatchMode=yes -o StrictHostKeyChecking=no -o ConnectTimeout=5 __TARGET__ exit 2>&1 | head -20
BASH,
        ],
        'hash-identify' => [
            'label' => 'Hash identifier',
            'category' => 'Passwords',
            'description' => 'Names the format of a hash from its length and prefix, so John and hashcat get the right mode.',
            'needs' => ['hash'],
            'command' => <<<'BASH'
# No command: the format is identified in the panel above.
BASH,
        ],
    ];

    /**
     * @return array<string, array{label: string, category: string, description: string, needs: array<int, string>}>
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach (self::TOOLS as $key => $tool) {
            $catalog[$key] = [
                'label' => $tool['label'],
                'category' => $tool['category'],
                'description' => $tool['description'],
                'needs' => $tool['needs'],
            ];
        }

        return $catalog;
    }

    /**
     * @param  array<int, string>  $selected
     * @param  array<string, string>  $values
     */
    public function render(array $selected, array $values): string
    {
        $selected = array_values(array_intersect($selected, array_keys(self::TOOLS)));

        if ($selected === []) {
            return '';
        }

        $values = [
            'hashfile' => $this->safe($values['hashfile'] ?? '', 'hashes.txt'),
            'hashmode' => $this->numeric($values['hashmode'] ?? '', '0'),
            'wordlist' => $this->safe($values['wordlist'] ?? '', '/usr/share/wordlists/rockyou.txt'),
            'userlist' => $this->safe($values['userlist'] ?? '', 'users.txt'),
            'target' => $this->safe($values['target'] ?? '', '192.0.2.10'),
            'path' => $this->safe($values['path'] ?? '', '/var/www'),
            'hash' => $this->safe($values['hash'] ?? '', ''),
        ];
        $values['targetsafe'] = preg_replace('/[^A-Za-z0-9.-]/', '_', $values['target']);

        $parts = [
            '#!/usr/bin/env bash',
            '# Dashy security toolkit',
            '# Review every line before running it, and only test systems you are allowed to test.',
            '',
            'set -uo pipefail',
            '',
        ];

        foreach ($selected as $key) {
            $command = self::TOOLS[$key]['command'];

            foreach ($values as $placeholder => $value) {
                $command = str_replace('__'.strtoupper($placeholder).'__', $value, $command);
            }

            $parts[] = '# ===== '.self::TOOLS[$key]['label'].' =====';
            $parts[] = trim($command);
            $parts[] = '';
        }

        return implode(PHP_EOL, $parts).PHP_EOL;
    }

    /**
     * Guess the hash format from its shape. Returns the most likely format first.
     *
     * @return array<int, array{format: string, detail: string, john: string|null, hashcat: string|null}>
     */
    public function identifyHash(string $hash): array
    {
        $hash = trim($hash);

        if ($hash === '') {
            return [];
        }

        // A "/" inside the character class would end the pattern, so these use "~".
        $guesses = match (true) {
            (bool) preg_match('~^\$2[aby]\$\d{2}\$[./A-Za-z0-9]{53}$~', $hash) => [
                ['format' => 'bcrypt', 'detail' => '60 characters, $2a$/$2b$ prefix.', 'john' => 'bcrypt', 'hashcat' => '3200'],
            ],
            (bool) preg_match('~^\$6\$(?:rounds=\d+\$)?[^\s$]+\$[./A-Za-z0-9]{86}$~', $hash) => [
                ['format' => 'sha512crypt', 'detail' => 'Linux /etc/shadow, $6$ prefix.', 'john' => 'sha512crypt', 'hashcat' => '1800'],
            ],
            (bool) preg_match('~^\$5\$(?:rounds=\d+\$)?[^\s$]+\$[./A-Za-z0-9]{43}$~', $hash) => [
                ['format' => 'sha256crypt', 'detail' => 'Linux /etc/shadow, $5$ prefix.', 'john' => 'sha256crypt', 'hashcat' => '7400'],
            ],
            (bool) preg_match('~^\$1\$(?:rounds=\d+\$)?[^\s$]+\$[./A-Za-z0-9]{22}$~', $hash) => [
                ['format' => 'md5crypt', 'detail' => 'BSD / old Linux, $1$ prefix.', 'john' => 'md5crypt', 'hashcat' => '500'],
            ],
            (bool) preg_match('~^\$argon2(id|i|d)\$~', $hash) => [
                ['format' => 'Argon2', 'detail' => 'Modern memory-hard format.', 'john' => 'argon2', 'hashcat' => '34000'],
            ],
            (bool) preg_match('/^[a-f0-9]{32}$/i', $hash) => [
                ['format' => 'MD5 or NTLM', 'detail' => '32 hex characters. NTLM has no markers, so try mode 1000 when MD5 fails.', 'john' => 'raw-md5', 'hashcat' => '0 (MD5), 1000 (NTLM)'],
            ],
            (bool) preg_match('/^[a-f0-9]{40}$/i', $hash) => [
                ['format' => 'SHA-1', 'detail' => '40 hex characters.', 'john' => 'raw-sha1', 'hashcat' => '100'],
            ],
            (bool) preg_match('/^[a-f0-9]{64}$/i', $hash) => [
                ['format' => 'SHA-256', 'detail' => '64 hex characters.', 'john' => 'raw-sha256', 'hashcat' => '1400'],
            ],
            (bool) preg_match('/^[a-f0-9]{128}$/i', $hash) => [
                ['format' => 'SHA-512', 'detail' => '128 hex characters.', 'john' => 'raw-sha512', 'hashcat' => '1700'],
            ],
            (bool) preg_match('~^[A-Za-z0-9+/]{22}==$~', $hash) => [
                ['format' => 'Base64 (16 bytes)', 'detail' => 'Looks like an encoded digest, not a hash format.', 'john' => null, 'hashcat' => null],
            ],
            default => [],
        };

        return $guesses;
    }

    private function safe(string $value, string $fallback): string
    {
        $value = trim($value);

        if ($value === '') {
            return $fallback;
        }

        // Only characters that cannot start a new command or escape the word.
        return preg_match('/^[A-Za-z0-9._:\/@%+~=-]+$/', $value) === 1 ? $value : escapeshellarg($value);
    }

    private function numeric(string $value, string $fallback): string
    {
        $value = trim($value);

        return preg_match('/^\d{1,6}$/', $value) === 1 ? $value : $fallback;
    }
}
