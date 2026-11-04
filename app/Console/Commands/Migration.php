<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Migration extends Command
{
    protected $signature = 'start:migration';

    protected $description = 'Start Migration';

    public function handle()
    {
        if (! config('constants.migration.is_migration_enabled')) {
            $this->info('Migration is disabled on this server.');

            return 0;
        }

        $this->info('Migration is enabled on this server.');

        // A failed migration leaves an incomplete schema, and every page that reads the
        // missing tables breaks. Report the failure instead of exiting successfully.
        $status = $this->call('migrate', ['--force' => true, '--isolated' => true]);
        if ($status !== 0) {
            $this->error('The database migrations did not complete.');
        }

        return $status;
    }
}
