<?php

namespace App\Services;

use App\Models\Server;

/**
 * Browses and edits files on a managed server over SSH, without Docker in the
 * middle: this is the host filesystem, which is what a file manager needs.
 *
 * Every path is passed through escapeshellarg and must be absolute, so a value
 * from the form can never escape into another command.
 */
class ServerFileManager
{
    public const MAX_READ_BYTES = 2 * 1024 * 1024;

    private const FIELD_SEPARATOR = "\t";

    public function __construct(private Server $server) {}

    /**
     * @return array<int, array{name: string, type: string, size: int, mtime: int, perms: string, owner: string, path: string}>
     */
    public function list(string $path): array
    {
        $path = $this->normalize($path);
        $format = '%F'.$this->tab().'%s'.$this->tab().'%Y'.$this->tab().'%a'.$this->tab().'%U'.$this->tab().'%n';
        $inner = 'cd '.escapeshellarg($path).' 2>/dev/null || exit 0; '
            .'find . ! -name . -prune -exec stat -c "'.$format.'" -- {} + 2>/dev/null; exit 0';

        $raw = (string) instant_remote_process([$inner], $this->server);
        $entries = [];

        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) < 6) {
                continue;
            }

            [$type, $size, $mtime, $perms, $owner, $name] = $parts;
            $name = ltrim($name, './');

            if ($name === '' || $name === '.') {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'type' => str_contains($type, 'directory') ? 'dir' : (str_contains($type, 'link') ? 'link' : 'file'),
                'size' => (int) $size,
                'mtime' => (int) $mtime,
                'perms' => $perms,
                'owner' => $owner,
                'path' => rtrim($path, '/').'/'.$name,
            ];
        }

        usort($entries, fn (array $a, array $b) => [$a['type'] === 'dir' ? 0 : 1, strtolower($a['name'])] <=> [$b['type'] === 'dir' ? 0 : 1, strtolower($b['name'])]);

        return $entries;
    }

    public function read(string $path): string
    {
        $path = $this->normalize($path);
        $raw = (string) instant_remote_process([
            '[ -f '.escapeshellarg($path).' ] || exit 1; '
            .'sz=$(stat -Lc %s -- '.escapeshellarg($path).') || exit 1; '
            .'if [ "$sz" -gt '.self::MAX_READ_BYTES.' ]; then echo TOOBIG; exit 0; fi; '
            .'base64 -- '.escapeshellarg($path).' || exit 1',
        ], $this->server);

        if (trim($raw) === 'TOOBIG') {
            throw new \RuntimeException('This file is larger than 2 MB. Download it instead.');
        }

        $decoded = base64_decode(preg_replace('/\s+/', '', $raw), true);

        if ($decoded === false) {
            throw new \RuntimeException('The file could not be read (permission denied or binary content).');
        }

        return $decoded;
    }

    public function write(string $path, string $content): void
    {
        $path = $this->normalize($path);
        $payload = base64_encode($content);

        if (strlen($payload) > 3 * 1024 * 1024) {
            throw new \RuntimeException('The content is too large to save from the browser.');
        }

        instant_remote_process([
            'mkdir -p '.escapeshellarg(dirname($path)),
            'printf %s '.escapeshellarg($payload).' | base64 -d > '.escapeshellarg($path),
        ], $this->server);
    }

    public function makeDirectory(string $path): void
    {
        instant_remote_process(['mkdir -p '.escapeshellarg($this->normalize($path))], $this->server);
    }

    public function createFile(string $path): void
    {
        instant_remote_process(['touch '.escapeshellarg($this->normalize($path))], $this->server);
    }

    public function rename(string $from, string $to): void
    {
        instant_remote_process(['mv '.escapeshellarg($this->normalize($from)).' '.escapeshellarg($this->normalize($to))], $this->server);
    }

    public function delete(string $path): void
    {
        instant_remote_process(['rm -rf -- '.escapeshellarg($this->normalize($path))], $this->server);
    }

    public function chmod(string $path, string $mode): void
    {
        if (preg_match('/^[0-7]{3,4}$/', $mode) !== 1) {
            throw new \RuntimeException('Permissions must be an octal mode, such as 644 or 755.');
        }

        instant_remote_process(['chmod '.$mode.' '.escapeshellarg($this->normalize($path))], $this->server);
    }

    public function compress(string $path): void
    {
        $path = $this->normalize($path);
        instant_remote_process(['cd '.escapeshellarg(dirname($path)).' && tar -czf '.escapeshellarg(basename($path).'.tar.gz').' '.escapeshellarg(basename($path))], $this->server);
    }

    public function defaultRoot(): string
    {
        return $this->server->isLocalhost() ? '/data/dashy' : '/';
    }

    private function normalize(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return $this->defaultRoot();
        }

        if ($path[0] !== '/') {
            throw new \RuntimeException('Use an absolute path.');
        }

        if (str_contains($path, "\0")) {
            throw new \RuntimeException('Invalid path.');
        }

        return $path;
    }

    private function tab(): string
    {
        return self::FIELD_SEPARATOR;
    }
}
