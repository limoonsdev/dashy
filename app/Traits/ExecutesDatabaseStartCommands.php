<?php

namespace App\Traits;

use App\Services\DatabaseStartCommandExecutor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

trait ExecutesDatabaseStartCommands
{
    private function executeDatabaseStartCommands(array $commands, Model $database, ?Activity $activity = null): Activity
    {
        if ($activity) {
            return app(DatabaseStartCommandExecutor::class)->execute($commands, $database, $activity);
        }

        return remote_process($commands, $database->destination->server, callEventOnFinish: 'DatabaseStatusChanged', queue: deployment_queue());
    }

    /**
     * Whether a `KEY=value` list already defines the exact key (not just a key or value containing it).
     *
     * @param  Collection<int, string>  $environmentVariables
     */
    private function hasEnvironmentVariable(Collection $environmentVariables, string $key): bool
    {
        return $environmentVariables->contains(fn ($variable) => str($variable)->before('=')->value() === $key);
    }

    /**
     * Adds the Dashy variables that applications and services also get. A key that the user
     * defines wins.
     *
     * @param  Collection<int, string>  $environmentVariables
     */
    private function addDashyEnvironmentVariables(Collection $environmentVariables): void
    {
        $definedKeys = $environmentVariables->map(fn ($variable) => str($variable)->before('=')->value());
        $dashyVariables = collect([
            'DASHY_RESOURCE_UUID' => $this->database->uuid,
            'DASHY_CONTAINER_NAME' => $this->database->uuid,
        ])->except($definedKeys->all())->merge(dashyDefaultEnvironmentVariables($this->database, $definedKeys));

        foreach ($dashyVariables as $key => $value) {
            $environmentVariables->push($key.'='.escapeDollarSign($value));
        }
    }
}
