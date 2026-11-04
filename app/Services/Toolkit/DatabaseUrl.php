<?php

namespace App\Services\Toolkit;

use App\Models\StandalonePostgresql;

class DatabaseUrl
{
    /**
     * Application flavours the connection builder knows about.
     *
     * @var array<string, string>
     */
    public const TARGETS = [
        'prisma' => 'Prisma',
        'laravel' => 'Laravel',
        'django' => 'Django',
        'node' => 'Node (pg / postgres.js)',
        'drizzle' => 'Drizzle',
        'generic' => 'Generic',
    ];

    /**
     * Build a connection string for one of Dashy's PostgreSQL databases.
     *
     * The internal host is the container UUID on the Dashy network; the
     * external host is the public server address and port.
     */
    public function fromPostgres(StandalonePostgresql $database, string $target = 'prisma', bool $external = false): ?string
    {
        $host = $database->uuid;
        $port = 5432;

        if ($external) {
            $server = data_get($database, 'destination.server');
            $ip = $server?->getIp;
            $publicPort = (int) ($database->public_port ?? 0);

            if (! $ip || $publicPort === 0) {
                return null;
            }

            $host = formatHostForUrl($ip);
            $port = $publicPort;
        }

        $sslMode = null;

        if ($database->enable_ssl) {
            $sslMode = $database->ssl_mode ?: 'require';
        }

        return $this->make(
            'postgresql',
            $host,
            $port,
            (string) $database->postgres_user,
            (string) $database->postgres_password,
            (string) $database->postgres_db,
            $target,
            $sslMode,
        );
    }

    /**
     * Assemble a URL that already carries the parameters an ORM expects, so a
     * Prisma user does not have to append schema/pool options by hand.
     */
    public function make(
        string $scheme,
        string $host,
        int $port,
        string $user,
        string $password,
        string $database,
        string $target = 'prisma',
        ?string $sslMode = null,
    ): string {
        $scheme = strtolower($scheme);
        $user = rawurlencode($user);
        $password = rawurlencode($password);
        $database = rawurlencode($database);

        $url = "{$scheme}://{$user}:{$password}@{$host}:{$port}/{$database}";

        $query = [];

        if ($target === 'prisma') {
            if (in_array($scheme, ['postgres', 'postgresql'], true)) {
                $query['schema'] = 'public';
            }

            $query['connection_limit'] = '5';
            $query['pool_timeout'] = '10';
        }

        if ($sslMode !== null && $sslMode !== '') {
            $query['sslmode'] = $sslMode;
        }

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * Redact the password so a connection string can be shown in a log line
     * without leaking the credential.
     */
    public function redact(string $url): string
    {
        return (string) preg_replace('#://([^:/@]+):[^@]*@#', '://$1:********@', $url);
    }
}
