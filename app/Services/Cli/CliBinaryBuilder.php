<?php

namespace App\Services\Cli;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Compiles the TypeScript CLI into standalone executables with Bun.
 *
 * A build is written straight to its final path, so no intermediate archive is
 * left behind. Binaries are reused for two hours (the same lifetime as the
 * signed download URL) and rebuilt after that. If Bun is not installed, the
 * exception explains exactly what to install instead of failing silently.
 */
class CliBinaryBuilder
{
    public const TTL_HOURS = 2;

    /**
     * Bun target, file name and label for every platform the console supports.
     *
     * @var array<string, array{target: string, file: string, label: string, note: string}>
     */
    public const PLATFORMS = [
        'linux-x64' => [
            'target' => 'bun-linux-x64',
            'file' => 'dashy',
            'label' => 'Linux · x64',
            'note' => 'glibc 2.28 or newer',
        ],
        'linux-arm64' => [
            'target' => 'bun-linux-arm64',
            'file' => 'dashy',
            'label' => 'Linux · arm64',
            'note' => 'Raspberry Pi 4/5, Ampere, Graviton',
        ],
        'darwin-x64' => [
            'target' => 'bun-darwin-x64',
            'file' => 'dashy',
            'label' => 'macOS · Intel',
            'note' => 'macOS 13 or newer',
        ],
        'darwin-arm64' => [
            'target' => 'bun-darwin-arm64',
            'file' => 'dashy',
            'label' => 'macOS · Apple silicon',
            'note' => 'M1 and later',
        ],
        'windows-x64' => [
            'target' => 'bun-windows-x64',
            'file' => 'dashy.exe',
            'label' => 'Windows · x64',
            'note' => 'Windows 10 and 11',
        ],
    ];

    public function directory(string $platform = ''): string
    {
        return storage_path('app/cli-builds'.($platform !== '' ? '/'.$platform : ''));
    }

    public function path(string $platform): string
    {
        return $this->directory($platform).'/'.self::PLATFORMS[$platform]['file'];
    }

    /**
     * Return a ready binary, building it only when the cached one is stale.
     */
    public function ensure(string $platform): string
    {
        $this->assertPlatform($platform);

        $path = $this->path($platform);

        if ($this->isFresh($path)) {
            return $path;
        }

        return $this->build($platform);
    }

    public function isFresh(string $path): bool
    {
        return File::exists($path) && File::lastModified($path) >= now()->subHours(self::TTL_HOURS)->getTimestamp();
    }

    /**
     * Compile the CLI and return the executable path.
     */
    public function build(string $platform): string
    {
        $this->assertPlatform($platform);

        $entry = base_path('cli/src/cli.ts');

        if (! File::exists($entry)) {
            throw new RuntimeException('The CLI sources are missing at cli/src/cli.ts.');
        }

        $bun = $this->bunBinary();
        $directory = $this->directory($platform);
        $path = $this->path($platform);

        File::ensureDirectoryExists($directory);

        // A cross-compile can outlive the default web request limit.
        @set_time_limit(600);

        // Remove a half-written binary from an interrupted previous build.
        if (File::exists($path)) {
            File::delete($path);
        }

        // Bun downloads the target runtime into this folder. Keep it out of
        // the shared home directory and remove it whether the build succeeds
        // or fails, so only the executable survives.
        $cache = storage_path('app/cli-builds/.bun-cache-'.Str::random(8));
        File::ensureDirectoryExists($cache);

        try {
            $result = Process::path(base_path())
                ->timeout(600)
                ->env([
                    'BUN_INSTALL_CACHE_DIR' => $cache,
                    'HOME' => $cache,
                ])
                ->run([
                    $bun,
                    'build',
                    '--compile',
                    '--target='.self::PLATFORMS[$platform]['target'],
                    '--outfile',
                    $path,
                    $entry,
                ]);
        } catch (\Throwable $e) {
            File::delete($path);

            throw $e;
        } finally {
            File::deleteDirectory($cache);
        }

        if (! $result->successful() || ! File::exists($path)) {
            $detail = trim($result->errorOutput()) ?: trim($result->output());

            throw new RuntimeException(
                'Bun could not build the '.$platform.' binary. '.
                ($detail !== '' ? $detail : 'Install Bun 1.1+ and set CLI_BUN_PATH if it is not on PATH.'),
            );
        }

        @chmod($path, 0755);
        $this->cleanup();

        return $path;
    }

    /**
     * Delete builds and temporary files older than the cache window. The binary
     * that was just produced is newer than the window, so it is kept.
     */
    public function cleanup(): void
    {
        $root = $this->directory();

        if (! File::isDirectory($root)) {
            return;
        }

        $cutoff = now()->subHours(self::TTL_HOURS)->getTimestamp();

        foreach (File::directories($root) as $directory) {
            $files = File::files($directory);
            $hasCurrentBinary = false;

            foreach ($files as $file) {
                if ($file->getMTime() < $cutoff) {
                    File::delete($file->getPathname());

                    continue;
                }

                if (in_array($file->getFilename(), array_column(self::PLATFORMS, 'file'), true)) {
                    $hasCurrentBinary = true;
                }
            }

            if (! $hasCurrentBinary && count(File::files($directory)) === 0) {
                File::deleteDirectory($directory);
            }
        }
    }

    public function downloadName(string $platform): string
    {
        $this->assertPlatform($platform);

        return $platform === 'windows-x64' ? 'dashy-windows-x64.exe' : 'dashy-'.$platform;
    }

    private function bunBinary(): string
    {
        $configured = (string) config('cli.bun_path', '');

        if ($configured !== '') {
            return $configured;
        }

        $candidate = PHP_OS_FAMILY === 'Windows' ? 'bun.exe' : 'bun';
        $path = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where '.$candidate.' 2>NUL' : 'command -v '.$candidate.' 2>/dev/null'));

        if ($path === '') {
            throw new RuntimeException(
                'Bun is not installed on this instance. Install Bun 1.1+ (https://bun.sh) or set CLI_BUN_PATH, '.
                'then use the install command instead of a binary.',
            );
        }

        return strtok($path, PHP_EOL) ?: $candidate;
    }

    private function assertPlatform(string $platform): void
    {
        if (! array_key_exists($platform, self::PLATFORMS)) {
            throw new RuntimeException('Unsupported platform: '.$platform);
        }
    }
}
