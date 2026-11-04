<?php

namespace App\Services\Toolkit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the public Modrinth API (no key needed), used to find mods,
 * plugins and datapacks for a Minecraft server and to produce install commands.
 */
class ModrinthClient
{
    public const CACHE_SECONDS = 300;

    public const LOADERS = [
        'fabric' => 'Fabric',
        'forge' => 'Forge',
        'neoforge' => 'NeoForge',
        'quilt' => 'Quilt',
        'paper' => 'Paper (plugins)',
        'purpur' => 'Purpur (plugins)',
        'spigot' => 'Spigot (plugins)',
    ];

    /**
     * @return array<int, array{id: string, slug: string, title: string, description: string, downloads: int, icon: string|null, author: string, url: string}>
     */
    public function search(string $query, string $loader = '', string $version = '', int $limit = 9): array
    {
        $query = trim($query);
        $loader = trim($loader);
        $version = trim($version);
        $limit = max(1, min(20, $limit));

        $cacheKey = 'dashy:modrinth:'.md5($query.'|'.$loader.'|'.$version.'|'.$limit);

        return Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($query, $loader, $version, $limit) {
            $facets = [['project_type:mod', 'project_type:plugin', 'project_type:datapack']];

            if ($loader !== '' && isset(self::LOADERS[$loader])) {
                $facets[] = ['categories:'.$loader];
            }

            if ($version !== '') {
                $facets[] = ['versions:'.$version];
            }

            $facets[] = ['server_side:required', 'server_side:optional'];

            try {
                $response = Http::timeout(10)
                    ->connectTimeout(5)
                    ->withHeaders(['User-Agent' => 'Dashy/'.$this->version()])
                    ->get('https://api.modrinth.com/v2/search', [
                        'query' => $query,
                        'limit' => $limit,
                        'index' => 'downloads',
                        'facets' => json_encode($facets),
                    ]);

                if ($response->failed()) {
                    Log::warning('Modrinth search failed', ['status' => $response->status()]);

                    return [];
                }

                return collect($response->json('hits', []))
                    ->map(fn (array $hit) => [
                        'id' => (string) data_get($hit, 'project_id', ''),
                        'slug' => (string) data_get($hit, 'slug', ''),
                        'title' => (string) data_get($hit, 'title', ''),
                        'description' => (string) data_get($hit, 'description', ''),
                        'downloads' => (int) data_get($hit, 'downloads', 0),
                        'icon' => data_get($hit, 'icon_url'),
                        'author' => (string) data_get($hit, 'author', ''),
                        'url' => 'https://modrinth.com/project/'.data_get($hit, 'slug', ''),
                    ])
                    ->filter(fn (array $hit) => $hit['id'] !== '')
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                Log::warning('Modrinth search failed', ['error' => $e->getMessage()]);

                return [];
            }
        });
    }

    /**
     * The newest file of a project that matches the loader (and the game version when given).
     *
     * @return array{filename: string, url: string, version: string}|null
     */
    public function latestFile(string $projectId, string $loader = '', string $version = ''): ?array
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'Dashy/'.$this->version()])
                ->get('https://api.modrinth.com/v2/project/'.$projectId.'/version', array_filter([
                    'loaders' => $loader !== '' ? json_encode([$loader]) : null,
                    'game_versions' => $version !== '' ? json_encode([$version]) : null,
                ]));

            if ($response->failed()) {
                return null;
            }

            $file = data_get($response->json(), '0.files.0');

            if (! is_array($file) || empty($file['url'])) {
                return null;
            }

            return [
                'filename' => (string) ($file['filename'] ?? basename((string) $file['url'])),
                'url' => (string) $file['url'],
                'version' => (string) data_get($response->json(), '0.version_number', ''),
            ];
        } catch (\Throwable $e) {
            Log::warning('Modrinth version lookup failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Shell commands that drop the selected projects into a Minecraft server's mods/ folder.
     *
     * @param  array<int, string>  $projectIds
     * @return array<int, array{title: string, command: string|null}>
     */
    public function installCommands(array $projectIds, string $loader = '', string $version = '', string $directory = 'mods'): array
    {
        $directory = preg_match('/^[A-Za-z0-9._\/-]+$/', $directory) === 1 ? $directory : 'mods';
        $plan = [];

        foreach (array_slice($projectIds, 0, 12) as $projectId) {
            if (preg_match('/^[A-Za-z0-9]{6,16}$/', $projectId) !== 1) {
                continue;
            }

            $file = $this->latestFile($projectId, $loader, $version);

            $plan[] = [
                'title' => $file['filename'] ?? $projectId,
                'command' => $file === null ? null : sprintf(
                    'curl -fsSL %s -o %s/%s',
                    escapeshellarg($file['url']),
                    $directory,
                    escapeshellarg($file['filename']),
                ),
            ];
        }

        return $plan;
    }

    private function version(): string
    {
        try {
            return (string) config('constants.dashy.version');
        } catch (\Throwable) {
            return 'dev';
        }
    }
}
