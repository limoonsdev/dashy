<?php

namespace App\Services\Security;

use App\Models\BlockedIp;
use Illuminate\Support\Facades\Cache;

/**
 * Heuristic abuse detector: obvious scanner paths, missing user agents and
 * bursts of 404s count as suspicious. The first offense blocks for 24 hours,
 * a second one is permanent until an admin approves the address again.
 */
class SuspiciousIpGuard
{
    public const SUSPICIOUS_PATHS = [
        '/.env', '/.git', '/wp-admin', '/wp-login', '/wp-content', '/phpmyadmin',
        '/pma', '/xmlrpc.php', '/vendor/phpunit', '/server-status', '/actuator',
        '/config.json', '/credentials', '/.aws', '/.ssh', '/cgi-bin', '/shell',
        '/eval-stdin.php', '/owa/', '/autodiscover', '/solr/', '/console',
    ];

    public function isBlocked(string $ip): bool
    {
        $entry = BlockedIp::query()->where('ip', $ip)->first();

        return $entry?->isBlocking() ?? false;
    }

    public function isSuspicious(string $path, ?string $userAgent, int $recentNotFound): bool
    {
        $path = strtolower($path);

        foreach (self::SUSPICIOUS_PATHS as $needle) {
            if (str_contains($path, $needle)) {
                return true;
            }
        }

        if ($userAgent === null || trim($userAgent) === '') {
            return true;
        }

        return $recentNotFound >= 15;
    }

    public function strike(string $ip, string $reason): BlockedIp
    {
        $entry = BlockedIp::query()->firstOrNew(['ip' => $ip]);
        $entry->hits = ($entry->hits ?? 0) + 1;
        $entry->reason = $reason;
        $entry->last_seen_at = now();

        if ($entry->hits >= 2 || $entry->permanent) {
            $entry->permanent = true;
            $entry->blocked_until = null;
        } else {
            $entry->blocked_until = now()->addHours(24);
        }

        $entry->approved = false;
        $entry->save();

        auditLog('security.ip_blocked', [
            'ip' => $ip,
            'reason' => $reason,
            'permanent' => (bool) $entry->permanent,
        ]);

        return $entry;
    }

    public function approve(string $ip): void
    {
        $entry = BlockedIp::query()->where('ip', $ip)->firstOrFail();
        $entry->forceFill([
            'approved' => true,
            'permanent' => false,
            'blocked_until' => null,
            'hits' => 0,
        ])->save();

        Cache::forget('dashy404:'.$ip);
        auditLog('security.ip_approved', ['ip' => $ip]);
    }

    public function countRecentNotFound(string $ip): int
    {
        return (int) Cache::get('dashy404:'.$ip, 0);
    }

    public function recordNotFound(string $ip): int
    {
        $key = 'dashy404:'.$ip;
        $count = $this->countRecentNotFound($ip) + 1;
        Cache::put($key, $count, now()->addMinutes(10));

        return $count;
    }
}
