<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dashy instances installed by the one-line installer run an image built from this
 * repository and tagged "local-<commit>", so their update source is the head commit of
 * the branch. Published images keep using the CDN versions file.
 *
 * The GitHub answer is cached for a few minutes so a dashboard with several Livewire
 * components does not spend the anonymous rate limit on every page view.
 */
class DashySourceRelease
{
    public const CACHE_KEY = 'dashy:source-release';

    public const CACHE_SECONDS = 600;

    public static function isSourceBuild(): bool
    {
        return self::currentCommit() !== null;
    }

    /**
     * The commit the running image was built from, from the "local-<sha>" version tag.
     */
    public static function currentCommit(): ?string
    {
        // config() reads .env; the image also bakes DASHY_VERSION as an environment
        // variable, which covers an instance whose config cache predates it.
        foreach ([(string) config('constants.dashy.version'), (string) env('DASHY_VERSION')] as $version) {
            if (preg_match('/^local-([0-9a-f]{7,40})$/', $version, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public static function repository(): string
    {
        return (string) (config('constants.dashy.source_repository') ?: 'limoonsdev/dashy');
    }

    public static function branch(): string
    {
        return (string) (config('constants.dashy.source_branch') ?: 'main');
    }

    public static function updateScriptUrl(): string
    {
        return 'https://raw.githubusercontent.com/'.self::repository().'/'.self::branch().'/scripts/update.sh';
    }

    /**
     * @return array{sha: string, short: string, message: string, date: string|null, url: string}|null
     */
    public static function latest(bool $fresh = false): ?array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            try {
                $response = Http::timeout(8)
                    ->connectTimeout(5)
                    ->withHeaders([
                        'Accept' => 'application/vnd.github+json',
                        'User-Agent' => 'Dashy',
                    ])
                    ->get('https://api.github.com/repos/'.self::repository().'/commits/'.self::branch());

                if ($response->failed()) {
                    Log::warning('Could not read the latest Dashy commit', ['status' => $response->status()]);

                    return null;
                }

                $sha = (string) $response->json('sha');
                if ($sha === '') {
                    return null;
                }

                return [
                    'sha' => $sha,
                    'short' => substr($sha, 0, 7),
                    'message' => str((string) $response->json('commit.message'))->before("\n")->limit(120)->toString(),
                    'date' => $response->json('commit.committer.date'),
                    'url' => 'https://github.com/'.self::repository().'/commit/'.$sha,
                ];
            } catch (\Throwable $e) {
                Log::warning('Could not read the latest Dashy commit', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Whether the branch head is a different commit than the one this image was built from.
     */
    public static function updateAvailable(bool $fresh = false): bool
    {
        $current = self::currentCommit();
        if ($current === null) {
            return false;
        }

        $latest = self::latest($fresh);
        if ($latest === null) {
            return false;
        }

        $sha = $latest['sha'];

        return ! str_starts_with($sha, $current) && ! str_starts_with($current, $sha);
    }

    /**
     * The image tag an update produces, in the same format the installer uses.
     */
    public static function targetVersion(bool $fresh = false): ?string
    {
        $latest = self::latest($fresh);

        return $latest === null ? null : 'local-'.$latest['short'];
    }
}
