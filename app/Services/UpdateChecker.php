<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * One update check that answers every question the settings page asks: which
 * release exists, which commit is on the branch, whether this instance is behind,
 * and what failed if GitHub could not be reached.
 *
 * Published images compare version numbers; source builds (tagged
 * "local-<sha>") compare the branch head commit with the commit that built the
 * running image.
 */
class UpdateChecker
{
    public const CACHE_KEY = 'dashy:update-check';

    public const CACHE_SECONDS = 60;

    /**
     * @return array{
     *     repository: string,
     *     branch: string,
     *     current: string,
     *     current_commit: string|null,
     *     channel: string,
     *     latest: string,
     *     latest_tag: string|null,
     *     latest_commit: array{sha: string, short: string, message: string, date: string|null, url: string}|null,
     *     release: array{tag: string, name: string, url: string, published_at: string|null}|null,
     *     available: bool,
     *     source: string,
     *     checked_at: string,
     *     error: string|null
     * }
     */
    public static function run(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => self::check($fresh));
    }

    /**
     * @return array<string, mixed>
     */
    public static function check(bool $fresh = false): array
    {
        $repository = DashySourceRelease::repository();
        $branch = DashySourceRelease::branch();
        $current = (string) config('constants.dashy.version');
        $currentCommit = DashySourceRelease::currentCommit();
        $settings = instanceSettings();
        $channel = (string) ($settings->update_channel ?: 'stable');
        $error = null;
        $tag = DashySourceRelease::latestTag($fresh);
        $commit = DashySourceRelease::latest($fresh);

        $release = $tag !== null ? [
            'tag' => $tag,
            'name' => $tag,
            'url' => "https://github.com/{$repository}/releases",
            'published_at' => null,
            'notes' => null,
        ] : null;

        $isSourceBuild = $currentCommit !== null;

        if ($isSourceBuild && $commit === null) {
            $error = 'Could not read the latest '.$branch.' commit from GitHub.'.(DashySourceRelease::lastError() ? ' '.DashySourceRelease::lastError() : '');
        } elseif (! $isSourceBuild && $tag === null) {
            $error = 'Could not read the latest Dashy release from GitHub.'.(DashySourceRelease::lastError() ? ' '.DashySourceRelease::lastError() : '');
        }
        $available = false;
        $source = 'release';
        $latest = $tag ?? $current;

        if ($channel === 'custom') {
            $source = 'custom';
            $latest = (string) $settings->update_custom_tag;
            $available = $latest !== '' && $latest !== $current;
            if ($latest === '') {
                $error = $error ?? 'The custom channel has no image tag yet.';
            }
        } elseif ($isSourceBuild) {
            $source = 'commit';
            $latest = $commit !== null ? 'local-'.$commit['short'] : $current;

            if ($commit !== null) {
                $available = ! str_starts_with($commit['sha'], $currentCommit)
                    && ! str_starts_with($currentCommit, $commit['sha']);
            }
        } elseif ($tag !== null) {
            $available = version_compare($tag, $current, '>');
        }

        // Keep the instance flag in sync so the badge in the sidebar agrees with this page.
        if ($channel !== 'custom' && ! $isSourceBuild && $tag !== null) {
            if ((bool) data_get($settings, 'new_version_available') !== $available) {
                $settings->update(['new_version_available' => $available]);
            }
        }

        return [
            'repository' => $repository,
            'branch' => $branch,
            'current' => $current,
            'current_commit' => $currentCommit,
            'channel' => $channel,
            'latest' => $latest,
            'latest_tag' => $tag,
            'latest_commit' => $commit,
            'release' => $release,
            'notes' => $release['notes'] ?? null,
            'url' => $release['url'] ?? null,
            'available' => $available,
            'source' => $source,
            'checked_at' => now()->toIso8601String(),
            'error' => $error,
        ];
    }

    /**
     * A short, human line for the sidebar badge and the upgrade panel.
     */
    public static function summary(bool $fresh = false): string
    {
        $check = self::run($fresh);

        if (($check['error'] ?? null) !== null && $check['latest_tag'] === null && $check['latest_commit'] === null) {
            return 'Update check failed';
        }

        return $check['available']
            ? 'Update available: '.$check['latest']
            : 'Up to date ('.$check['current'].')';
    }
}
