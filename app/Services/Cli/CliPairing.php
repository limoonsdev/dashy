<?php

namespace App\Services\Cli;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Short-lived pairing codes that let the `dashy` CLI obtain an API token without
 * asking for a password in a terminal.
 *
 * The code itself carries nothing: the server keeps the user, the team, the
 * abilities and the expiry under the code in the cache, and the code is removed
 * the moment a terminal redeems it. A code is therefore single use and expires
 * even if nobody uses it.
 */
class CliPairing
{
    public const PREFIX = 'DASHY';

    public const MINUTES = 10;

    public const ABILITIES = [
        'read' => ['read'],
        'write' => ['read', 'write'],
        'deploy' => ['read', 'write', 'deploy'],
    ];

    /** Ambiguous characters are left out: the code is read from a screen and typed. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function cacheKey(string $code): string
    {
        return 'dashy:cli:pair:'.strtoupper(trim($code));
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array{code: string, expires_at: string, abilities: array<int, string>}
     */
    public static function issue(User $user, ?Team $team, array $abilities, int $minutes = self::MINUTES, ?int $accessDays = 14): array
    {
        $abilities = array_values(array_intersect($abilities, ['read', 'write', 'deploy', 'root']));
        if ($abilities === []) {
            $abilities = ['read'];
        }

        $code = self::format($abilities);

        Cache::put(self::cacheKey($code), [
            'user_id' => $user->id,
            'team_id' => $team?->id,
            'abilities' => $abilities,
            'access_days' => $accessDays,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes($minutes));

        return [
            'code' => $code,
            'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
            'abilities' => $abilities,
        ];
    }

    /**
     * @return array{user_id: int, team_id: int|null, abilities: array<int, string>, created_at: string}|null
     */
    public static function peek(string $code): ?array
    {
        $payload = Cache::get(self::cacheKey($code));

        return is_array($payload) ? $payload : null;
    }

    /**
     * Reads and removes the code in one step, so two terminals cannot share it.
     *
     * @return array{user_id: int, team_id: int|null, abilities: array<int, string>, access_days: int|null, created_at: string}|null
     */
    public static function redeem(string $code): ?array
    {
        $key = self::cacheKey($code);
        $payload = Cache::pull($key);

        return is_array($payload) ? $payload : null;
    }

    public static function forget(string $code): void
    {
        Cache::forget(self::cacheKey($code));
    }

    /**
     * The first group encodes the abilities so a code that grants deploy is
     * recognisable at a glance: R = read, W = read + write, D = read + write + deploy.
     *
     * @param  array<int, string>  $abilities
     */
    private static function format(array $abilities): string
    {
        $mode = match (true) {
            in_array('deploy', $abilities, true) => 'D',
            in_array('write', $abilities, true) => 'W',
            default => 'R',
        };

        return implode('-', [
            self::PREFIX,
            $mode.self::random(3),
            self::random(4),
        ]);
    }

    private static function random(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $value = '';

        for ($i = 0; $i < $length; $i++) {
            $value .= self::ALPHABET[random_int(0, $max)];
        }

        return Str::upper($value);
    }
}
