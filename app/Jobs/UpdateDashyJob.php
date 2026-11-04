<?php

namespace App\Jobs;

use App\Actions\Server\UpdateDashy;
use App\Models\Server;
use App\Services\DashySourceRelease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateDashyJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        try {
            if (DashySourceRelease::isSourceBuild()) {
                if (! DashySourceRelease::updateAvailable(fresh: true)) {
                    Log::info('No new Dashy commit. Skipping update.');

                    return;
                }
            } else {
                CheckForUpdatesJob::dispatchSync();
                $settings = instanceSettings();
                if (! $settings->new_version_available) {
                    Log::info('No new version available. Skipping update.');

                    return;
                }
            }

            $server = Server::findOrFail(0);
            if (! $server) {
                Log::error('Server not found. Cannot proceed with update.');

                return;
            }

            Log::info('Starting Dashy update process...');
            UpdateDashy::run(false); // false means it's not a manual update

            Log::info('Dashy upgrade script started. Result: /data/dashy/source/.upgrade-status, log: /data/dashy/source/upgrade-*.log.');
        } catch (\Throwable $e) {
            Log::error('UpdateDashyJob failed: '.$e->getMessage());
            // Consider implementing a notification to administrators
        }
    }
}
