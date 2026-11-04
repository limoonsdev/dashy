<?php

namespace App\Services\Toolkit;

use App\Services\Security\MalwareScanner;
use ZipArchive;

class ArchiveImporter
{
    public const MAX_ENTRIES = 20000;

    /** 512 MiB of uncompressed content per archive. */
    public const MAX_BYTES = 536870912;

    /**
     * Extract a zip into a fresh directory and report what is inside it.
     *
     * Every entry is written by hand instead of calling extractTo(), so a
     * crafted archive cannot escape the destination through "..", an absolute
     * path, a Windows drive path, or a symlink entry.
     *
     * @return array{root: string, files: int, bytes: int}
     */
    public function extract(string $archivePath, string $destination, ?MalwareScanner $scanner = null): array
    {
        // Scan before anything touches the archive: a detected threat never
        // reaches the extraction loop, and a required-but-unreachable scanner
        // fails closed instead of silently accepting the file.
        $scanner?->assertClean($archivePath);

        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is not installed on this server.');
        }

        if (! is_file($archivePath)) {
            throw new \RuntimeException('The uploaded archive could not be read.');
        }

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new \RuntimeException('The archive is not a readable zip file.');
        }

        try {
            if ($zip->numFiles === 0) {
                throw new \RuntimeException('The archive is empty.');
            }

            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new \RuntimeException('The archive contains too many files (limit '.self::MAX_ENTRIES.').');
            }

            if (! is_dir($destination) && ! @mkdir($destination, 0755, true) && ! is_dir($destination)) {
                throw new \RuntimeException('The import directory could not be created.');
            }

            $files = 0;
            $bytes = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);

                if (! is_array($stat)) {
                    continue;
                }

                $name = (string) ($stat['name'] ?? '');

                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                if ($this->isSymlink($zip, $index)) {
                    throw new \RuntimeException('The archive contains a symbolic link, which is not allowed.');
                }

                $relative = $this->safeRelativePath($name);

                if ($relative === null) {
                    throw new \RuntimeException('The archive contains an unsafe path: '.$name);
                }

                $bytes += (int) ($stat['size'] ?? 0);

                if ($bytes > self::MAX_BYTES) {
                    throw new \RuntimeException('The archive expands to more than 512 MB.');
                }

                $target = $destination.DIRECTORY_SEPARATOR.$relative;
                $parent = dirname($target);

                if (! is_dir($parent) && ! @mkdir($parent, 0755, true) && ! is_dir($parent)) {
                    throw new \RuntimeException('A directory in the archive could not be created.');
                }

                $stream = $zip->getStream($name);

                if (! is_resource($stream)) {
                    throw new \RuntimeException('An entry in the archive could not be read.');
                }

                $output = @fopen($target, 'wb');

                if ($output === false) {
                    fclose($stream);

                    throw new \RuntimeException('An entry in the archive could not be written.');
                }

                stream_copy_to_stream($stream, $output, self::MAX_BYTES);
                fclose($stream);
                fclose($output);
                $files++;
            }

            return [
                'root' => $this->resolveRoot($destination),
                'files' => $files,
                'bytes' => $bytes,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * Detect the runtime and the build pack from the extracted tree.
     *
     * @return array{runtime: string, framework: ?string, buildpack: string, needs_database: bool, database_engine: ?string, port: ?int, notes: array<int, string>}
     */
    public function detectStack(string $root): array
    {
        $has = fn (string $path): bool => file_exists($root.DIRECTORY_SEPARATOR.$path);
        $read = fn (string $path): string => $this->readSmallFile($root.DIRECTORY_SEPARATOR.$path);

        $stack = [
            'runtime' => 'unknown',
            'framework' => null,
            'buildpack' => 'none',
            'needs_database' => false,
            'database_engine' => null,
            'port' => null,
            'notes' => [],
        ];

        if ($has('docker-compose.yml') || $has('docker-compose.yaml') || $has('compose.yml') || $has('compose.yaml')) {
            return array_merge($stack, [
                'runtime' => 'docker',
                'buildpack' => 'dockercompose',
                'notes' => ['A Compose file was found. Dashy can import it as a service stack, so every container starts together.'],
            ]);
        }

        if ($has('Dockerfile')) {
            return array_merge($stack, [
                'runtime' => 'docker',
                'buildpack' => 'dockerfile',
                'notes' => ['A Dockerfile was found. This is the most predictable build pack for a zip import.'],
            ]);
        }

        // FiveM server resources ship a manifest, not a web app.
        if ($has('fxmanifest.lua') || $has('__resource.lua') || $has('server.cfg')) {
            return array_merge($stack, [
                'runtime' => 'fivem',
                'framework' => 'FiveM',
                'buildpack' => 'dockerfile',
                'port' => 30120,
                'notes' => [
                    'A FiveM resource or server was found. Deploy it with the fxserver image and open UDP/TCP 30120.',
                    'Keymaster licence keys stay in environment variables, never in the repo.',
                ],
            ]);
        }

        if ($has('prisma/schema.prisma')) {
            $schema = $read('prisma/schema.prisma');
            $engine = $this->prismaProvider($schema);

            $stack = array_merge($stack, [
                'runtime' => $has('package.json') ? 'node' : 'unknown',
                'framework' => 'Prisma',
                'needs_database' => $engine !== 'sqlite',
                'database_engine' => $engine,
                'notes' => [
                    'Prisma schema found (provider: '.($engine ?? 'unknown').').',
                    'Run "npx prisma migrate deploy" as a pre-deployment command, then "npx prisma generate" during the build.',
                    'Set DATABASE_URL from the Dashy database page so the connection string already carries schema=public and pool limits.',
                ],
            ]);
        }

        if ($has('package.json')) {
            $manifest = $read('package.json');
            $decoded = json_decode($manifest, true);
            $dependencies = is_array($decoded)
                ? array_merge($decoded['dependencies'] ?? [], $decoded['devDependencies'] ?? [])
                : [];

            $framework = null;
            $port = null;

            if (isset($dependencies['next'])) {
                $framework = 'Next.js';
                $port = 3000;
            } elseif (isset($dependencies['nuxt'])) {
                $framework = 'Nuxt';
                $port = 3000;
            } elseif (isset($dependencies['@sveltejs/kit'])) {
                $framework = 'SvelteKit';
                $port = 3000;
            } elseif (isset($dependencies['astro'])) {
                $framework = 'Astro';
                $port = 4321;
            } elseif (isset($dependencies['vite'])) {
                $framework = 'Vite';
                $port = 5173;
            } elseif (isset($dependencies['express'])) {
                $framework = 'Express';
                $port = 3000;
            } elseif (isset($dependencies['discord.js'])) {
                $framework = 'Discord bot (discord.js)';
            }

            $notes = ['Node project detected.'];

            if (isset($dependencies['discord.js'])) {
                $notes[] = 'This is a Discord bot: it needs no public port. Keep the bot token in environment variables and set the restart policy to always.';
            }

            if ($has('pnpm-lock.yaml')) {
                $notes[] = 'pnpm lockfile found. Install with pnpm.';
            } elseif ($has('yarn.lock')) {
                $notes[] = 'Yarn lockfile found. Install with yarn.';
            } elseif ($has('bun.lockb') || $has('bun.lock')) {
                $notes[] = 'Bun lockfile found. Install with bun.';
            }

            return array_merge($stack, [
                'runtime' => 'node',
                'framework' => $framework ?? ($stack['framework'] ?? null),
                'buildpack' => $stack['buildpack'] === 'none' ? 'railpack' : $stack['buildpack'],
                'port' => $port,
                'notes' => array_merge($stack['notes'], $notes),
            ]);
        }

        if ($has('requirements.txt') || $has('pyproject.toml') || $has('Pipfile')) {
            $dependencies = strtolower($read('requirements.txt').$read('pyproject.toml').$read('Pipfile'));
            $framework = null;
            $port = null;

            if (str_contains($dependencies, 'fastapi')) {
                $framework = 'FastAPI';
                $port = 8000;
            } elseif (str_contains($dependencies, 'django')) {
                $framework = 'Django';
                $port = 8000;
            } elseif (str_contains($dependencies, 'flask')) {
                $framework = 'Flask';
                $port = 5000;
            } elseif (str_contains($dependencies, 'discord.py') || str_contains($dependencies, 'nextcord')) {
                $framework = 'Discord bot (Python)';
            }

            $notes = [
                'Python project detected. Nixpacks is deprecated, so add a Dockerfile or deploy with the Railpack build pack.',
                'Remember to install dependencies from requirements.txt or pyproject.toml in the build command.',
            ];

            if ($framework !== null && str_contains($framework, 'Discord')) {
                $notes[] = 'This bot needs no public port. Store the token as an environment variable.';
            }

            return array_merge($stack, [
                'runtime' => 'python',
                'framework' => $framework,
                'buildpack' => 'railpack',
                'port' => $port,
                'notes' => array_merge($stack['notes'], $notes),
            ]);
        }

        if ($has('composer.json')) {
            $composer = $read('composer.json');
            $isLaravel = str_contains($composer, 'laravel/framework');

            return array_merge($stack, [
                'runtime' => 'php',
                'framework' => $isLaravel ? 'Laravel' : 'PHP',
                'buildpack' => 'railpack',
                'port' => 8000,
                'notes' => [
                    $isLaravel
                        ? 'Laravel project detected. Point the document root at public/ and run migrations on deploy.'
                        : 'PHP project detected. Add a Dockerfile when the project needs extra extensions.',
                ],
            ]);
        }

        if ($has('go.mod')) {
            return array_merge($stack, ['runtime' => 'go', 'buildpack' => 'railpack', 'port' => 8080, 'notes' => ['Go module detected.']]);
        }

        if ($has('Cargo.toml')) {
            return array_merge($stack, ['runtime' => 'rust', 'buildpack' => 'railpack', 'port' => 8080, 'notes' => ['Rust project detected.']]);
        }

        if ($has('Gemfile')) {
            return array_merge($stack, ['runtime' => 'ruby', 'buildpack' => 'railpack', 'port' => 3000, 'notes' => ['Ruby project detected.']]);
        }

        if ($has('index.html')) {
            return array_merge($stack, [
                'runtime' => 'static',
                'buildpack' => 'static',
                'notes' => ['Static site detected. Dashy serves it through the built-in static build pack.'],
            ]);
        }

        $stack['notes'][] = 'No known project file was found. Add a Dockerfile for full control.';

        return $stack;
    }

    /**
     * Return a path that is guaranteed to stay inside the destination.
     */
    public function safeRelativePath(string $entryName): ?string
    {
        $entryName = str_replace('\\', '/', $entryName);

        if ($entryName === '' || str_contains($entryName, "\0")) {
            return null;
        }

        // Absolute POSIX path or a Windows drive/UNC path.
        if (str_starts_with($entryName, '/') || preg_match('/^[a-z]:/i', $entryName) || str_starts_with($entryName, '//')) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $entryName) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * When the zip wraps everything in one top-level folder, import from it so
     * the user does not have to know the folder name.
     */
    private function resolveRoot(string $destination): string
    {
        $entries = array_values(array_diff(scandir($destination) ?: [], ['.', '..']));

        if (count($entries) === 1 && is_dir($destination.DIRECTORY_SEPARATOR.$entries[0])) {
            return $destination.DIRECTORY_SEPARATOR.$entries[0];
        }

        return $destination;
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        if (! method_exists($zip, 'getExternalAttributesIndex')) {
            return false;
        }

        $operatingSystem = 0;
        $attributes = 0;

        if (! @$zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
            return false;
        }

        return (($attributes >> 16) & 0170000) === 0120000;
    }

    private function readSmallFile(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $contents = @file_get_contents($path, false, null, 0, 262144);

        return is_string($contents) ? $contents : '';
    }

    private function prismaProvider(string $schema): ?string
    {
        if (preg_match('/provider\s*=\s*"([a-z0-9]+)"/i', $schema, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }
}
