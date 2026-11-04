<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Dashy instances installed by the one-line installer run an image built from this
 * repository and tagged "local-<commit>", so their update source is the head commit of
 * the branch. Published images keep using the CDN versions file.
 *
 * GitHub's anonymous API allows only 60 requests per hour per address, and several
 * instances can share that address. The REST answer therefore gets a long cache, an
 * ETag (a 304 does not spend quota), and a fallback to the public commit feed and
 * `git ls-remote` when GitHub answers 403 or 429. Update detection must not turn an
 * exhausted quota into "you are up to date".
 */
class DashySourceRelease
{
    public const CACHE_KEY = 'dashy:source-release';

    public const TAG_CACHE_KEY = 'dashy:source-release-tag';

    public const CACHE_SECONDS = 600;

    private const GITHUB_API_BLOCK_KEY = 'dashy:github:api-blocked';

    private static ?string $lastError = null;

    /**
     * The last GitHub failure that left no data to fall back to.
     */
    public static function lastError(): ?string
    {
        return self::$lastError;
    }

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
        // Accepted forms: "local-<sha>" and "<release>-<sha>" (4.7.0-abc1234).
        foreach ([(string) config('constants.dashy.version'), (string) env('DASHY_VERSION')] as $version) {
            $candidate = preg_replace('/^(?:local-|[0-9]+(?:\.[0-9]+)+-)/', '', trim($version));

            if (is_string($candidate) && preg_match('/^[0-9a-f]{7,40}$/', $candidate)) {
                return $candidate;
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
            self::$lastError = null;

            // The public Atom feed is not part of the REST API quota and already
            // carries the head SHA, message and date. Read it first, so a normal
            // dashboard never spends the 60 anonymous API requests per hour.
            $commit = self::commitFromAtom();
            if ($commit !== null) {
                return $commit;
            }

            $commit = self::commitFromApi();
            if ($commit !== null) {
                self::$lastError = null;

                return $commit;
            }

            $commit = self::commitFromGit();
            if ($commit !== null) {
                self::$lastError = null;

                return $commit;
            }

            self::$lastError ??= 'GitHub did not return the latest commit.';

            return null;
        });
    }

    /**
     * The newest published release tag of this repository, without the leading "v".
     * release.json is served from raw.githubusercontent.com, which is not the REST API,
     * so the normal path costs no API quota. The API stays as a fallback only.
     */
    public static function latestTag(bool $fresh = false): ?string
    {
        if ($fresh) {
            Cache::forget(self::TAG_CACHE_KEY);
        }

        return Cache::remember(self::TAG_CACHE_KEY, self::CACHE_SECONDS, function () {
            self::$lastError = null;

            $tag = self::tagFromReleaseFile();
            if ($tag !== null) {
                return $tag;
            }

            // release.json can be unavailable (raw CDN, a proxy, a local CA
            // bundle). The releases feed is public HTML infrastructure, not the
            // REST API, and still names the newest tag.
            $tag = self::tagFromReleasesFeed();
            if ($tag !== null) {
                return $tag;
            }

            $release = self::githubJson('releases/latest');
            $tag = ltrim((string) data_get($release, 'tag_name'), 'v');
            if ($tag !== '') {
                return $tag;
            }

            $tags = self::githubJson('tags', ['per_page' => 1]);
            $tag = ltrim((string) data_get($tags, '0.name'), 'v');
            if ($tag !== '') {
                return $tag;
            }

            self::$lastError ??= 'GitHub did not return a release tag.';

            return null;
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

    /**
     * @return array{sha: string, short: string, message: string, date: string|null, url: string}|null
     */
    private static function commitFromApi(): ?array
    {
        $data = self::githubJson('commits/'.self::branch());
        $sha = strtolower((string) data_get($data, 'sha'));

        if (preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
            return null;
        }

        return self::formatCommit(
            $sha,
            (string) data_get($data, 'commit.message'),
            data_get($data, 'commit.committer.date'),
        );
    }

    /**
     * Read the same commit from the public Atom feed of the branch.
     *
     * @return array{sha: string, short: string, message: string, date: string|null, url: string}|null
     */
    private static function commitFromAtom(): ?array
    {
        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'Dashy'])
                ->get('https://github.com/'.self::repository().'/commits/'.self::branch().'.atom');
        } catch (\Throwable $e) {
            Log::warning('Could not read the Dashy commit feed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Could not read the Dashy commit feed', ['status' => $response->status()]);

            return null;
        }

        if (preg_match('/<entry\b[^>]*>(.*?)<\/entry>/s', $response->body(), $entry) !== 1) {
            return null;
        }

        $entry = $entry[1];
        $sha = '';
        $message = '';
        $date = null;

        if (preg_match('/<id>\s*tag:github\.com,2008:Grit::Commit\/([0-9a-f]{40})\s*<\/id>/i', $entry, $matches) === 1) {
            $sha = strtolower($matches[1]);
        }
        if (preg_match('/<title[^>]*>(.*?)<\/title>/s', $entry, $matches) === 1) {
            $message = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (preg_match('/<updated>([^<]+)<\/updated>/', $entry, $matches) === 1) {
            $date = trim($matches[1]);
        }

        if ($sha === '') {
            return null;
        }

        return self::formatCommit($sha, $message, $date);
    }

    /**
     * Last resort when the REST API is unavailable and the Atom feed cannot be read.
     * `git ls-remote` asks the Git endpoint directly, which is not the API quota either.
     *
     * @return array{sha: string, short: string, message: string, date: string|null, url: string}|null
     */
    private static function commitFromGit(): ?array
    {
        try {
            $result = Process::timeout(12)->run([
                'git',
                'ls-remote',
                '--exit-code',
                'https://github.com/'.self::repository().'.git',
                'refs/heads/'.self::branch(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not read the Dashy branch with git', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
            if (preg_match('/^([0-9a-f]{40})\s+refs\/heads\//i', trim($line), $matches) === 1) {
                return self::formatCommit(strtolower($matches[1]), '', null);
            }
        }

        return null;
    }

    /**
     * @return array{sha: string, short: string, message: string, date: string|null, url: string}
     */
    private static function formatCommit(string $sha, string $message, mixed $date): array
    {
        // Only the subject line fits the update panel; the body is not shown.
        $message = trim(explode("\n", str_replace("\r", '', $message))[0] ?? '');
        if ($message === '') {
            $message = 'Latest commit on '.self::branch();
        }

        return [
            'sha' => $sha,
            'short' => substr($sha, 0, 7),
            'message' => mb_substr($message, 0, 120),
            'date' => is_string($date) && $date !== '' ? $date : null,
            'url' => 'https://github.com/'.self::repository().'/commit/'.$sha,
        ];
    }

    /**
     * release.json is part of the branch and is updated with the stable version, so
     * reading it first avoids the release API for every dashboard load.
     */
    private static function tagFromReleaseFile(): ?string
    {
        try {
            $response = Http::timeout(6)
                ->connectTimeout(4)
                ->withHeaders(['User-Agent' => 'Dashy'])
                ->get('https://raw.githubusercontent.com/'.self::repository().'/'.self::branch().'/release.json');
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $version = data_get($response->json(), 'stable.dashy');
        if (! is_string($version) || preg_match('/^\d+(?:\.\d+)+/', $version) !== 1) {
            return null;
        }

        return ltrim($version, 'v');
    }

    /**
     * Read the newest release tag from the public releases Atom feed, without using
     * the REST API quota.
     */
    private static function tagFromReleasesFeed(): ?string
    {
        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'Dashy'])
                ->get('https://github.com/'.self::repository().'/releases.atom');
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if (preg_match('/<entry\b[^>]*>(.*?)<\/entry>/s', $response->body(), $entry) !== 1) {
            return null;
        }

        $tag = '';
        if (preg_match('/<id>\s*tag:github\.com,2008:Repository\/[0-9]+\/([^<]+)\s*<\/id>/i', $entry[1], $matches) === 1) {
            $tag = urldecode(trim($matches[1]));
        } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $entry[1], $matches) === 1) {
            $tag = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $tag = ltrim($tag, 'v');

        return preg_match('/^\d+(?:\.\d+)+/', $tag) === 1 ? $tag : null;
    }

    /**
     * GET a GitHub REST endpoint with a cached ETag. A 304 answer does not count
     * against the API quota, which keeps a dashboard that opens often well under
     * the anonymous limit.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private static function githubJson(string $path, array $query = []): ?array
    {
        $key = sha1($path.'?'.http_build_query($query));
        $bodyKey = 'dashy:github:body:'.$key;
        $etagKey = 'dashy:github:etag:'.$key;

        // A previous successful answer is still usable while the API is blocked.
        $cached = Cache::get($bodyKey);
        if (Cache::has(self::GITHUB_API_BLOCK_KEY)) {
            return is_array($cached) ? $cached : null;
        }

        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Dashy',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        $token = (string) (config('services.github.token') ?: env('GITHUB_TOKEN', ''));
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $etag = Cache::get($etagKey);
        if (is_string($etag) && $etag !== '') {
            $headers['If-None-Match'] = $etag;
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->withHeaders($headers)
                ->get(self::apiUrl($path), $query);
        } catch (\Throwable $e) {
            self::$lastError = 'GitHub could not be reached: '.$e->getMessage();
            Log::warning('Could not reach the GitHub API', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->status() === 304 && is_array($cached)) {
            return $cached;
        }

        if ($response->successful()) {
            $body = $response->json();
            if (! is_array($body)) {
                return null;
            }

            Cache::put($bodyKey, $body, 86400);
            $newEtag = $response->header('ETag');
            if (is_string($newEtag) && $newEtag !== '') {
                Cache::put($etagKey, $newEtag, 86400);
            }

            return $body;
        }

        if (in_array($response->status(), [403, 429], true)) {
            self::blockGitHubApi($response);
        }

        self::$lastError = 'GitHub API returned HTTP '.$response->status().'.';
        Log::warning('GitHub API request failed', [
            'path' => $path,
            'status' => $response->status(),
            'rate_limit_remaining' => $response->header('X-RateLimit-Remaining'),
        ]);

        return null;
    }

    /**
     * Stop asking an exhausted API until its own reset time. The fallbacks keep the
     * update check useful in the meantime; without this every page load would send
     * another doomed request.
     */
    private static function blockGitHubApi(Response $response): void
    {
        $retryAfter = (int) $response->header('Retry-After');
        $resetAt = (int) $response->header('X-RateLimit-Reset');
        $seconds = $retryAfter > 0
            ? $retryAfter
            : ($resetAt > time() ? $resetAt - time() : 300);

        Cache::put(self::GITHUB_API_BLOCK_KEY, true, max(60, min($seconds, 900)));
    }

    private static function apiUrl(string $path): string
    {
        return 'https://api.github.com/repos/'.self::repository().'/'.ltrim($path, '/');
    }
}
