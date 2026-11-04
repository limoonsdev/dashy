<?php

namespace App\Console\Commands;

use App\Models\MaintenanceSetting;
use App\Models\Server;
use App\Services\Maintenance\MaintenanceCleaner;
use Illuminate\Console\Command;

class RunMaintenance extends Command
{
    protected $signature = 'dashy:maintenance {--force : Run even when no schedule window is due}';

    protected $description = 'Run the daily or weekly clean-up on every server of every team';

    public function handle(): int
    {
        $cleaner = new MaintenanceCleaner;
        $ran = 0;

        foreach (MaintenanceSetting::query()->get() as $settings) {
            if (! $this->option('force') && ! $cleaner->isDue($settings)) {
                continue;
            }

            foreach (Server::query()->where('team_id', $settings->team_id)->get() as $server) {
                $results = $cleaner->run($server, (array) $settings->options);
                $failed = collect($results)->where('ok', false)->count();
                $this->line($server->name.': '.count($results).' task(s), '.$failed.' failed');
                $ran++;
            }

            $settings->forceFill([
                'last_daily_at' => now(),
                'last_weekly_at' => now(),
            ])->save();
        }

        $this->info($ran.' server(s) cleaned.');

        return self::SUCCESS;
    }
}
