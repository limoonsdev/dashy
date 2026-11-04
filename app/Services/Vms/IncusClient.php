<?php

namespace App\Services\Vms;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Incus (LXD-compatible) API client: images are downloaded from a
 * remote by name, which is what replaces Proxmox ISO storage here.
 *
 * Auth is a bearer token (Incus 6.x trust tokens) or a client certificate pair
 * passed through INCUS_CERT / INCUS_KEY. Only the endpoints the VM page needs
 * are implemented.
 */
class IncusClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('vms.incus', []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function instances(): array
    {
        $response = $this->request('GET', '/1.0/instances?recursion=1');

        return (array) ($response->json('metadata') ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function images(): array
    {
        $response = $this->request('GET', '/1.0/images?recursion=1');

        return (array) ($response->json('metadata') ?? []);
    }

    public function create(string $name, int $cores = 2, int $memoryMb = 2048, int $diskGb = 20): array
    {
        $response = $this->request('POST', '/1.0/instances', [
            'name' => $name,
            'source' => [
                'type' => 'image',
                'alias' => (string) ($this->config['image'] ?? 'debian/12'),
                'server' => 'https://images.linuxcontainers.org',
                'protocol' => 'simplestreams',
            ],
            'config' => [
                'limits.cpu' => (string) max(1, $cores),
                'limits.memory' => max(512, $memoryMb).'MiB',
            ],
            'devices' => [
                'root' => [
                    'type' => 'disk',
                    'pool' => (string) ($this->config['storage'] ?? 'default'),
                    'size' => max(5, $diskGb).'GiB',
                ],
                'eth0' => [
                    'type' => 'nic',
                    'name' => 'eth0',
                    'network' => (string) ($this->config['network'] ?? 'incusbr0'),
                ],
            ],
        ]);

        return (array) $response->json();
    }

    public function start(string $name): array
    {
        return (array) $this->request('PUT', '/1.0/instances/'.rawurlencode($name).'/state', [
            'action' => 'start',
            'timeout' => 60,
            'force' => false,
        ])->json();
    }

    public function stop(string $name, bool $force = false): array
    {
        return (array) $this->request('PUT', '/1.0/instances/'.rawurlencode($name).'/state', [
            'action' => 'stop',
            'timeout' => 60,
            'force' => $force,
        ])->json();
    }

    public function delete(string $name): array
    {
        return (array) $this->request('DELETE', '/1.0/instances/'.rawurlencode($name))->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $path, array $payload = []): Response
    {
        $base = rtrim((string) ($this->config['url'] ?? ''), '/');

        if ($base === '') {
            throw new \RuntimeException('INCUS_URL is not configured.');
        }

        $request = Http::timeout((int) ($this->config['timeout'] ?? 60))
            ->acceptJson()
            ->withHeaders(['Content-Type' => 'application/json']);

        if (filled($this->config['token'] ?? null)) {
            $request = $request->withToken((string) $this->config['token']);
        }

        $options = ['verify' => (bool) ($this->config['verify_tls'] ?? true)];

        if (filled($this->config['cert'] ?? null) && filled($this->config['key'] ?? null)) {
            $options['cert'] = (string) $this->config['cert'];
            $options['ssl_key'] = (string) $this->config['key'];
        }

        $response = $request->withOptions($options)->send($method, $base.$path.'?project='.rawurlencode((string) ($this->config['project'] ?? 'default')), [
            'json' => $payload,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Incus returned HTTP '.$response->status().': '.$response->body());
        }

        return $response;
    }
}
