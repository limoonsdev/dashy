<?php

namespace App\Services\Toolkit;

class DnsLookup
{
    /** @var array<string, int> */
    private const TYPE_MAP = [
        'A' => DNS_A,
        'AAAA' => DNS_AAAA,
        'CNAME' => DNS_CNAME,
        'MX' => DNS_MX,
        'TXT' => DNS_TXT,
        'NS' => DNS_NS,
    ];

    /**
     * Services that encode an IPv4 address inside the hostname itself.
     *
     * @var array<int, string>
     */
    private const IP_EMBEDDING_SUFFIXES = ['sslip.io', 'nip.io', 'xip.io'];

    /**
     * @param  array<int, string>  $types
     * @return array<int, array{type: string, value: string, ttl: int}>
     */
    public function records(string $host, array $types = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS']): array
    {
        $host = $this->normalizeHost($host);

        if ($host === null) {
            return [];
        }

        $records = [];

        foreach ($types as $type) {
            $type = strtoupper($type);

            if (! isset(self::TYPE_MAP[$type])) {
                continue;
            }

            $found = @dns_get_record($host, self::TYPE_MAP[$type]);

            if (! is_array($found)) {
                continue;
            }

            foreach ($found as $record) {
                $value = match ($type) {
                    'A' => $record['ip'] ?? null,
                    'AAAA' => $record['ipv6'] ?? null,
                    'CNAME', 'NS' => $record['target'] ?? null,
                    'MX' => isset($record['pri'], $record['target']) ? $record['pri'].' '.$record['target'] : null,
                    'TXT' => $record['txt'] ?? null,
                    default => null,
                };

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $records[] = [
                    'type' => $type,
                    'value' => $value,
                    'ttl' => (int) ($record['ttl'] ?? 0),
                ];
            }
        }

        return $records;
    }

    /**
     * @return array<int, string>
     */
    public function addresses(string $host): array
    {
        $addresses = [];

        foreach ($this->records($host, ['A', 'AAAA']) as $record) {
            $addresses[] = $record['value'];
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Turn user input into a bare hostname, or null when it cannot be one.
     */
    public function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host) ?? $host;
        $host = explode('/', $host)[0];
        $host = explode('?', $host)[0];
        $host = explode('#', $host)[0];
        $host = rtrim($host, '.');

        if ($host === '' || strlen($host) > 253) {
            return null;
        }

        if (! preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host)) {
            return null;
        }

        return $host;
    }

    /**
     * Extract the IPv4 address that a hostname publishes in plain sight.
     *
     * sslip.io, nip.io and xip.io resolve names like 203-0-113-7.sslip.io by
     * reading the address out of the label, so the hostname itself is the leak.
     */
    public function embeddedIp(string $host): ?string
    {
        $host = $this->normalizeHost($host);

        if ($host === null) {
            return null;
        }

        // Dashed form in the leftmost label: 203-0-113-7.example.com
        if (preg_match('/^((?:\d{1,3}-){3}\d{1,3})\./', $host, $matches)) {
            $candidate = str_replace('-', '.', $matches[1]);

            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $candidate;
            }
        }

        if (! $this->usesIpEmbeddingService($host)) {
            return null;
        }

        // Dotted form: 203.0.113.7.sslip.io, including a leading label such as
        // www. Strip the service suffix, then read the address from what is left.
        $prefix = $host;

        foreach (self::IP_EMBEDDING_SUFFIXES as $suffix) {
            if ($host === $suffix) {
                return null;
            }

            if (str_ends_with($host, '.'.$suffix)) {
                $prefix = substr($host, 0, -strlen('.'.$suffix));

                break;
            }
        }

        if (preg_match('/(\d{1,3}(?:\.\d{1,3}){3})/', $prefix, $matches)
            && filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $matches[1];
        }

        return null;
    }

    public function hostnameRevealsIp(string $host): bool
    {
        return $this->embeddedIp($host) !== null;
    }

    public function usesIpEmbeddingService(string $host): bool
    {
        $host = $this->normalizeHost($host);

        if ($host === null) {
            return false;
        }

        foreach (self::IP_EMBEDDING_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the hostname either spells out the server address or resolves
     * to it directly.
     */
    public function exposesServerIp(string $host, ?string $serverIp): bool
    {
        if ($serverIp === null || $serverIp === '') {
            return false;
        }

        if ($this->embeddedIp($host) === $serverIp) {
            return true;
        }

        return in_array($serverIp, $this->addresses($host), true);
    }
}
