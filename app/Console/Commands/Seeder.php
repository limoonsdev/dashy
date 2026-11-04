<?php

namespace App\Console\Commands;

use App\Models\InstanceSettings;
use Illuminate\Console\Command;

class Seeder extends Command
{
    protected $signature = 'start:seeder';

    protected $description = 'Start Seeder';

    public function handle()
    {
        if (! config('constants.seeder.is_seeder_enabled')) {
            $this->info('Seeder is disabled on this server.');

            return 0;
        }

        $this->info('Seeder is enabled on this server.');

        try {
            $status = $this->call('db:seed', ['--class' => 'ProductionSeeder', '--force' => true]);
        } catch (\Throwable $e) {
            $status = 1;
            $this->error("The seeder failed: {$e->getMessage()}");
        }

        if ($status !== 0 || InstanceSettings::query()->find(0) === null) {
            // Keep the container running: app:init seeds the missing root data and
            // InstanceSettings::get() creates the settings row, so the instance still
            // answers instead of turning every page into a 404.
            $this->error('The seeder did not create the root instance settings.');
        }

        return 0;
    }
}
