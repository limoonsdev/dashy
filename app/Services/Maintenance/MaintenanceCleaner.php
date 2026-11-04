<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceSetting;
use App\Models\Server;

/**
 * Runs conservative clean-up commands. Nothing deletes live data: Docker
 * pruning is limited to unused objects older than a week, the journal is
 * vacuumed, caches are dropped and old temp files are removed.
 */
class MaintenanceCleaner
{
    /**
     * @return array<string, string>
     */
    public function commands(): array
    {
        return [
            'docker' => 'docker system prune -af --filter "until=168h" >/dev/null 2>&1; echo docker-ok',
            'journal' => 'journalctl --vacuum-time=7d >/dev/null 2>&1; echo journal-ok',
            'apt' => '(apt-get clean || true) >/dev/null 2>&1; (apt-get autoremove -y || true) >/dev/null 2>&1; echo apt-ok',
            'tmp' => 'find /tmp -mindepth 1 -maxdepth 1 -mtime +7 -exec rm -rf -- {} + 2>/dev/null; echo tmp-ok',
            'logs' => 'find /var/log -type f -name "*.log" -size +50M -exec truncate -s 0 -- {} + 2>/dev/null; echo logs-ok',
        ];
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, array{option: string, ok: bool, output: string}>
     */
    public function run(Server $server, array $options): array
    {
        $allowed = array_intersect(array_keys($this->commands()), $options);
        $results = [];

        foreach ($this->commands() as $option => $command) {
            if (! in_array($option, $allowed, true)) {
                continue;
            }

            try {
                $output = (string) instant_remote_process([$command], $server, false, false, 600);
                $results[] = ['option' => $option, 'ok' => str_contains($output, $option.'-ok'), 'output' => trim($output)];
            } catch (\Throwable $e) {
                $results[] = ['option' => $option, 'ok' => false, 'output' => $e->getMessage()];
            }
        }

        auditLog('maintenance.cleaned', [
            'server' => $server->uuid,
            'options' => $allowed,
            'team_id' => $server->team_id,
        ]);

        return $results;
    }

    public function dailyDue(MaintenanceSetting $settings): bool
    {
        if (! $settings->daily_enabled) {
            return false;
        }

        $now = now();
        $window = $now->copy()->setTimeFromTimeString($settings->daily_time);

        return $now->greaterThanOrEqualTo($window)
            && ($settings->last_daily_at === null || $settings->last_daily_at->lt($now->copy()->startOfDay()));
    }

    public function weeklyDue(MaintenanceSetting $settings): bool
    {
        if (! $settings->weekly_enabled) {
            return false;
        }

        $now = now();
        $window = $now->copy()->setTimeFromTimeString($settings->weekly_time);

        return strtolower($now->englishDayOfWeek) === strtolower($settings->weekly_day)
            && $now->greaterThanOrEqualTo($window)
            && ($settings->last_weekly_at === null || $settings->last_weekly_at->lt($now->copy()->startOfWeek()));
    }

    public function isDue(MaintenanceSetting $settings): bool
    {
        return $this->dailyDue($settings) || $this->weeklyDue($settings);
    }
}
