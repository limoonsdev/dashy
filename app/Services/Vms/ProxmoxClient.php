<?php

namespace App\Services\Vms;

/**
 * Minimal Proxmox VE API client.
 *
 * Auth: API token (PVEAPIToken) when token_id/token_secret are set, otherwise a
 * ticket + CSRF token obtained from /access/ticket. The HTTP transport is
 * injectable so the client can be exercised against a fake server without
 * booting Laravel.
 */
class ProxmoxClient
{
    /** @var callable(string, string, array<int, string>, ?string): array{status: int, body: string}|null */
    private $transport;

    private ?string $ticket = null;

    private ?string $csrf = null;

    public function __construct(
        private string $baseUrl,
        private ?string $tokenId = null,
        private ?string $tokenSecret = null,
        private ?string $username = null,
        private ?string $password = null,
        private bool $verifyTls = true,
        private int $timeout = 30,
        private int $taskTimeout = 900,
        ?callable $transport = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport ?? $this->curlTransport(...);
    }

    public static function fromConfig(): self
    {
        $config = config('vms.proxmox', []);

        return new self(
            baseUrl: (string) ($config['url'] ?? ''),
            tokenId: $config['token_id'] ?? null,
            tokenSecret: $config['token_secret'] ?? null,
            username: $config['username'] ?? null,
            password: $config['password'] ?? null,
            verifyTls: (bool) ($config['verify_tls'] ?? true),
            timeout: (int) ($config['timeout'] ?? 30),
            taskTimeout: (int) ($config['task_timeout'] ?? 900),
        );
    }

    public function configured(): bool
    {
        $hasToken = $this->has($this->tokenId) && $this->has($this->tokenSecret);
        $hasTicket = $this->has($this->username) && $this->has($this->password);

        return $this->baseUrl !== '' && ($hasToken || $hasTicket);
    }

    public function version(): array
    {
        return (array) $this->request('GET', '/version');
    }

    public function nextVmid(): int
    {
        return (int) $this->request('GET', '/cluster/nextid');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listVms(string $node): array
    {
        $data = $this->request('GET', "/nodes/{$this->segment($node)}/qemu");

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function vmStatus(string $node, int $vmid): array
    {
        return (array) $this->request('GET', "/nodes/{$this->segment($node)}/qemu/{$vmid}/status/current");
    }

    /**
     * @param  array<string, mixed>  $params
     * @return string The task UPID.
     */
    public function createVm(string $node, array $params): string
    {
        return (string) $this->request('POST', "/nodes/{$this->segment($node)}/qemu", $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return string The task UPID.
     */
    public function cloneVm(string $node, int $template, array $params): string
    {
        return (string) $this->request('POST', "/nodes/{$this->segment($node)}/qemu/{$template}/clone", $params);
    }

    /**
     * @return string The task UPID.
     */
    public function startVm(string $node, int $vmid): string
    {
        return (string) $this->request('POST', "/nodes/{$this->segment($node)}/qemu/{$vmid}/status/start");
    }

    /**
     * @return string The task UPID.
     */
    public function shutdownVm(string $node, int $vmid): string
    {
        return (string) $this->request('POST', "/nodes/{$this->segment($node)}/qemu/{$vmid}/status/shutdown");
    }

    /**
     * @return string The task UPID.
     */
    public function stopVm(string $node, int $vmid): string
    {
        return (string) $this->request('POST', "/nodes/{$this->segment($node)}/qemu/{$vmid}/status/stop");
    }

    /**
     * @return string The task UPID.
     */
    public function deleteVm(string $node, int $vmid): string
    {
        return (string) $this->request('DELETE', "/nodes/{$this->segment($node)}/qemu/{$vmid}");
    }

    /**
     * @return string The task UPID.
     */
    public function resizeDisk(string $node, int $vmid, string $disk, int $gigabytes): string
    {
        return (string) $this->request('PUT', "/nodes/{$this->segment($node)}/qemu/{$vmid}/resize", [
            'disk' => $disk,
            'size' => $gigabytes.'G',
        ]);
    }

    /**
     * Download a cloud image into Proxmox ISO storage.
     *
     * @return string The task UPID.
     */
    public function downloadUrl(string $node, string $storage, string $url, string $filename, ?string $checksum = null): string
    {
        $params = [
            'url' => $url,
            'filename' => $filename,
            'content' => 'iso',
            'verify-certificates' => 1,
        ];

        if ($checksum !== null && trim($checksum) !== '') {
            $params['checksum'] = $checksum;
            $params['checksum-algorithm'] = 'sha256';
        }

        return (string) $this->request(
            'POST',
            "/nodes/{$this->segment($node)}/storage/{$this->segment($storage)}/download-url",
            $params,
        );
    }

    /**
     * Poll a task until it stops.
     *
     * @return array<string, mixed>
     */
    public function waitForTask(string $node, string $upid, ?int $timeout = null): array
    {
        $timeout ??= $this->taskTimeout;
        $deadline = time() + max(1, $timeout);

        do {
            $status = (array) $this->request(
                'GET',
                "/nodes/{$this->segment($node)}/tasks/".rawurlencode($upid).'/status',
            );

            if (($status['status'] ?? null) === 'stopped') {
                $exit = (string) ($status['exitstatus'] ?? '');

                if ($exit !== 'OK') {
                    throw new ProxmoxException("Proxmox task failed: {$exit}");
                }

                return $status;
            }

            usleep(500000);
        } while (time() < $deadline);

        throw new ProxmoxException('Proxmox task timed out.');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function request(string $method, string $path, array $params = [], bool $auth = true): mixed
    {
        $headers = ['Accept: application/json'];
        $body = null;
        $url = $this->baseUrl.'/api2/json'.$path;

        if ($auth) {
            if ($this->has($this->tokenId) && $this->has($this->tokenSecret)) {
                $headers[] = "Authorization: PVEAPIToken={$this->tokenId}={$this->tokenSecret}";
            } else {
                $this->ensureTicket();
                $headers[] = 'Cookie: PVEAuthCookie='.$this->ticket;

                if ($method !== 'GET') {
                    $headers[] = 'CSRFPreventionToken: '.$this->csrf;
                }
            }
        }

        if ($method === 'GET' || $method === 'DELETE') {
            if ($params !== []) {
                $url .= '?'.http_build_query($params);
            }
        } else {
            $body = http_build_query($params);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $response = ($this->transport)($method, $url, $headers, $body);
        $decoded = json_decode($response['body'], true);

        if (! is_array($decoded)) {
            throw new ProxmoxException(
                "Proxmox returned a non-JSON response (HTTP {$response['status']}).",
                $response['status'],
                $response['body'],
            );
        }

        if ($response['status'] >= 400 || isset($decoded['errors'])) {
            $message = $decoded['message'] ?? null;

            if (is_array($decoded['errors'] ?? null)) {
                $message = implode(' ', array_map('strval', $decoded['errors']));
            }

            throw new ProxmoxException(
                'Proxmox API error: '.($message ?? 'HTTP '.$response['status']),
                $response['status'],
                $response['body'],
            );
        }

        return $decoded['data'] ?? null;
    }

    private function ensureTicket(): void
    {
        if ($this->has($this->ticket)) {
            return;
        }

        $data = $this->request('POST', '/access/ticket', [
            'username' => $this->username,
            'password' => $this->password,
        ], false);

        $this->ticket = $data['ticket'] ?? null;
        $this->csrf = $data['CSRFPreventionToken'] ?? null;

        if (! $this->has($this->ticket)) {
            throw new ProxmoxException('Proxmox did not return an authentication ticket.');
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{status: int, body: string}
     */
    private function curlTransport(string $method, string $url, array $headers, ?string $body): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new ProxmoxException('cURL could not be initialised.');
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if ($raw === false) {
            $message = curl_error($handle);
            curl_close($handle);

            throw new ProxmoxException('Proxmox connection failed: '.$message);
        }

        curl_close($handle);

        return ['status' => $status, 'body' => (string) $raw];
    }

    private function has(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function segment(string $value): string
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value)) {
            throw new ProxmoxException("Invalid Proxmox identifier [{$value}].");
        }

        return $value;
    }
}
