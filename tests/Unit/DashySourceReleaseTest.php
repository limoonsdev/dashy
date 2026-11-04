<?php

use App\Services\DashySourceRelease;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'constants.dashy.source_repository' => 'limoonsdev/dashy',
        'constants.dashy.source_branch' => 'main',
        'services.github.token' => null,
    ]);
});

it('reads the latest commit from the Atom feed without spending API quota', function () {
    $sha = str_repeat('a', 40);

    Http::fake(function (Request $request) use ($sha) {
        if (str_contains($request->url(), 'api.github.com')) {
            return Http::response(
                ['message' => 'API rate limit exceeded'],
                403,
                ['X-RateLimit-Reset' => (string) (time() + 3600)],
            );
        }

        if (str_ends_with($request->url(), '/commits/main.atom')) {
            return Http::response(<<<XML
                <feed>
                    <entry>
                        <id>tag:github.com,2008:Grit::Commit/{$sha}</id>
                        <title>Fix the updater</title>
                        <updated>2026-11-04T10:00:00Z</updated>
                    </entry>
                </feed>
                XML);
        }

        return Http::response('', 404);
    });

    $latest = DashySourceRelease::latest(fresh: true);

    expect($latest)->not->toBeNull()
        ->and($latest['sha'])->toBe($sha)
        ->and($latest['short'])->toBe('aaaaaaa')
        ->and($latest['message'])->toBe('Fix the updater');

    // The API still answers 403 in this fake, but it must not have been called.
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api.github.com'));
});

it('falls back to the API when the commit feed is unavailable', function () {
    $sha = str_repeat('b', 40);

    Http::fake(function (Request $request) use ($sha) {
        if (str_ends_with($request->url(), '/commits/main.atom')) {
            return Http::response('', 404);
        }

        if (str_contains($request->url(), 'api.github.com')) {
            return Http::response([
                'sha' => $sha,
                'commit' => [
                    'message' => "Commit from the API\n\nBody",
                    'committer' => ['date' => '2026-11-04T11:00:00Z'],
                ],
            ]);
        }

        return Http::response('', 404);
    });

    $latest = DashySourceRelease::latest(fresh: true);

    expect($latest)->not->toBeNull()
        ->and($latest['sha'])->toBe($sha)
        ->and($latest['message'])->toBe('Commit from the API');
});

it('reads the release version from release.json without spending API quota', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'raw.githubusercontent.com') && str_ends_with($request->url(), '/release.json')) {
            return Http::response(['stable' => ['dashy' => '4.7.0']]);
        }

        return Http::response(['message' => 'rate limited'], 429);
    });

    expect(DashySourceRelease::latestTag(fresh: true))->toBe('4.7.0');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api.github.com'));
});

it('falls back to the releases feed when release.json is unavailable', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'raw.githubusercontent.com') && str_ends_with($request->url(), '/release.json')) {
            return Http::response('', 404);
        }

        if (str_ends_with($request->url(), '/releases.atom')) {
            return Http::response(<<<'XML'
                <feed>
                    <entry>
                        <id>tag:github.com,2008:Repository/1445455833/v4.7.0</id>
                        <title>v4.7.0</title>
                    </entry>
                </feed>
                XML);
        }

        return Http::response('', 429);
    });

    expect(DashySourceRelease::latestTag(fresh: true))->toBe('4.7.0');
});
