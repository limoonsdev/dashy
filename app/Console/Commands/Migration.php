<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Migration extends Command
{
    protected $signature = 'start:migration';

    protected $description = 'Start Migration';

    /**
     * The database container can still be starting when this oneshot runs, and a run that
     * gives up leaves every page that reads the missing tables broken (oauth_settings first,
     * on the login screen). Wait for the connection and retry instead.
     */
    private const CONNECT_TIMEOUT_SECONDS = 120;

    private const MAX_ATTEMPTS = 3;

    public function handle()
    {
        if (! config('constants.migration.is_migration_enabled')) {
            $this->info('Migration is disabled on this server.');

            return 0;
        }

        $this->info('Migration is enabled on this server.');

        if (! $this->waitForDatabase()) {
            $this->error('The database did not accept connections in time, the migrations were skipped.');

            return 1;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $status = $this->runMigrations();

            if ($status === 0) {
                return 0;
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                $this->warn('Migrations failed (attempt '.$attempt.'/'.self::MAX_ATTEMPTS.'), retrying in 5 seconds.');
                sleep(5);
            }
        }

        // A failed migration leaves an incomplete schema, so report it instead of exiting
        // successfully: the container must not start as if the database was ready.
        $this->error('The database migrations did not complete.');

        return 1;
    }

    private function runMigrations(): int
    {
        try {
            // No --isolated: the lock lives in the cache store (Redis), which is not
            // guaranteed to be up when s6 starts this oneshot. Postgres serializes
            // concurrent migrations by itself.
            return $this->call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::error('Could not run the migrations', ['error' => $e->getMessage()]);
            $this->error("Could not run the migrations: {$e->getMessage()}");

            return 1;
        }
    }

    private function waitForDatabase(): bool
    {
        $deadline = time() + self::CONNECT_TIMEOUT_SECONDS;

        do {
            try {
                DB::connection()->getPdo();

                return true;
            } catch (\Throwable $e) {
                $this->warn('Waiting for the database: '.$e->getMessage());
                sleep(2);
            }
        } while (time() < $deadline);

        return false;
    }
}
