<?php

use App\Actions\Database\StartDragonfly;
use App\Actions\Database\StartPostgresql;
use App\Jobs\ApplicationDeploymentJob;
use App\Livewire\Project\Shared\EnvironmentVariable\All;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\StandaloneDragonfly;
use App\Models\StandalonePostgresql;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::query()->firstOrCreate(['id' => 0]);
    Bus::fake();

    $this->team = Team::factory()->create();
    $this->server = Server::factory()->create(['team_id' => $this->team->id]);
    $this->destination = $this->server->standaloneDockers()->firstOrFail();
    $project = Project::factory()->create(['team_id' => $this->team->id, 'name' => 'Shop $live']);
    $this->environment = Environment::factory()->create(['project_id' => $project->id, 'name' => 'staging']);
});

function dashyDefaultsPostgres(array $attributes = []): StandalonePostgresql
{
    return StandalonePostgresql::forceCreate([
        'uuid' => 'dashy-defaults-db',
        'name' => 'Main database',
        'image' => 'postgres:16-alpine',
        'postgres_password' => 'password',
        'environment_id' => test()->environment->id,
        'destination_id' => test()->destination->id,
        'destination_type' => test()->destination->getMorphClass(),
        ...$attributes,
    ]);
}

/**
 * @return array<int, string>
 */
function dashyDefaultsDatabaseEnvironment(object $action, $database): array
{
    $action->database = $database->fresh();

    return (new ReflectionMethod($action, 'generate_environment_variables'))->invoke($action);
}

/**
 * @return array<int, string>
 */
function dashyDefaultsApplicationEnv(Application $application, bool $forBuildTime = false, ?Server $deploymentServer = null): array
{
    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);
    $job = $reflection->newInstanceWithoutConstructor();
    $queue = Mockery::mock(ApplicationDeploymentQueue::class);
    $queue->shouldReceive('addLogEntry')->andReturnNull();

    $properties = [
        'application' => $application->fresh(),
        'application_deployment_queue' => $queue,
        'build_pack' => 'nixpacks',
        'mainServer' => $deploymentServer ?? $application->destination->server,
        'pull_request_id' => 0,
        'commit' => 'HEAD',
        'branch' => 'main',
        'container_name' => 'dashy-defaults-app',
        'saved_outputs' => collect(),
    ];
    foreach ($properties as $property => $value) {
        $reflection->getProperty($property)->setValue($job, $value);
    }

    if ($forBuildTime) {
        return collect($reflection->getMethod('generate_dashy_env_variables')->invoke($job, true))
            ->map(fn ($value, $key) => "{$key}={$value}")->values()->all();
    }

    return collect($reflection->getMethod('generate_runtime_environment_variables')->invoke($job))->all();
}

function dashyDefaultsApplication(): Application
{
    return Application::factory()->create([
        'name' => 'My web app',
        'environment_id' => test()->environment->id,
        'destination_id' => test()->destination->id,
        'destination_type' => test()->destination->getMorphClass(),
    ]);
}

function dashyDefaultsService(string $environmentYaml = ''): Service
{
    $service = Service::factory()->create([
        'name' => 'blog',
        'environment_id' => test()->environment->id,
        'server_id' => test()->server->id,
        'destination_id' => test()->destination->id,
        'destination_type' => test()->destination->getMorphClass(),
        'docker_compose_raw' => "services:\n  app:\n    image: nginx:latest\n{$environmentYaml}",
    ]);
    ServiceApplication::create(['name' => 'app', 'service_id' => $service->id]);

    return $service;
}

test('a database gets the same Dashy variables as other resources, with compose-safe values', function () {
    $environment = dashyDefaultsDatabaseEnvironment(new StartPostgresql, dashyDefaultsPostgres());

    expect($environment)
        ->toContain('DASHY_RESOURCE_UUID=dashy-defaults-db')
        ->toContain('DASHY_CONTAINER_NAME=dashy-defaults-db')
        ->toContain('DASHY_APP_NAME=Main database')
        ->toContain('DASHY_PROJECT_NAME=Shop $$live')
        ->toContain('DASHY_ENVIRONMENT_NAME=staging')
        ->toContain("DASHY_SERVER_IP={$this->server->ip}");
});

test('a Dragonfly database also gets the Dashy variables', function () {
    $database = StandaloneDragonfly::forceCreate([
        'uuid' => 'dashy-defaults-dragonfly',
        'name' => 'Cache',
        'image' => 'docker.dragonflydb.io/dragonflydb/dragonfly',
        'dragonfly_password' => 'password',
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => $this->destination->getMorphClass(),
    ]);

    expect(dashyDefaultsDatabaseEnvironment(new StartDragonfly, $database))
        ->toContain('DASHY_RESOURCE_UUID=dashy-defaults-dragonfly')
        ->toContain('DASHY_APP_NAME=Cache');
});

test('a database variable that the user defines wins over the Dashy variable', function () {
    $database = dashyDefaultsPostgres();
    $database->runtime_environment_variables()->create(['key' => 'DASHY_APP_NAME', 'value' => 'custom']);

    $appNames = collect(dashyDefaultsDatabaseEnvironment(new StartPostgresql, $database))
        ->filter(fn ($variable) => str_starts_with($variable, 'DASHY_APP_NAME='));

    expect($appNames->values()->all())->toBe(['DASHY_APP_NAME=custom']);
});

test('a database without the option gets no default variables but keeps its identity variables', function () {
    $environment = dashyDefaultsDatabaseEnvironment(new StartPostgresql, dashyDefaultsPostgres(['include_dashy_default_variables' => false]));

    expect(collect($environment)->first(fn ($variable) => str_starts_with($variable, 'DASHY_APP_NAME=')))->toBeNull()
        ->and(collect($environment)->first(fn ($variable) => str_starts_with($variable, 'DASHY_SERVER_IP=')))->toBeNull()
        ->and($environment)->toContain('DASHY_RESOURCE_UUID=dashy-defaults-db');
});

test('an application gets the default variables as quoted lines in its runtime .env file', function () {
    $environment = dashyDefaultsApplicationEnv(dashyDefaultsApplication());

    expect($environment)
        ->toContain('DASHY_APP_NAME="My web app"')
        ->toContain('DASHY_PROJECT_NAME="Shop \\$live"')
        ->toContain('DASHY_ENVIRONMENT_NAME="staging"')
        ->toContain("DASHY_SERVER_IP=\"{$this->server->ip}\"");
});

test('an application on an additional server gets the IP of the server it runs on', function () {
    $additionalServer = Server::factory()->create(['team_id' => $this->team->id, 'ip' => '203.0.113.7']);

    expect(dashyDefaultsApplicationEnv(dashyDefaultsApplication(), deploymentServer: $additionalServer))
        ->toContain('DASHY_SERVER_IP="203.0.113.7"');
});

test('an application build does not get the default variables', function () {
    $environment = dashyDefaultsApplicationEnv(dashyDefaultsApplication(), forBuildTime: true);

    expect(collect($environment)->first(fn ($variable) => str_starts_with($variable, 'DASHY_APP_NAME=')))->toBeNull()
        ->and(collect($environment)->first(fn ($variable) => str_starts_with($variable, 'DASHY_RESOURCE_UUID=')))->not->toBeNull();
});

test('an application without the option gets no default variables', function () {
    $application = dashyDefaultsApplication();
    $application->settings->update(['include_dashy_default_variables' => false]);

    $environment = dashyDefaultsApplicationEnv($application);

    expect(collect($environment)->first(fn ($variable) => str_starts_with($variable, 'DASHY_APP_NAME=')))->toBeNull();
});

test('a service variable defined in the compose file wins over the Dashy variable', function () {
    $service = dashyDefaultsService("    environment:\n      DASHY_APP_NAME: custom\n");

    $environment = data_get(serviceParser($service), 'services.app.environment');

    expect($environment['DASHY_APP_NAME'])->toBe('custom')
        ->and($environment['DASHY_PROJECT_NAME'])->toBe('Shop $$live');
});

test('a service without the option gets no default variables', function () {
    $service = dashyDefaultsService();
    $service->forceFill(['include_dashy_default_variables' => false])->save();

    $environment = collect(data_get(serviceParser($service->fresh()), 'services.app.environment'));

    expect($environment->has('DASHY_APP_NAME'))->toBeFalse()
        ->and($environment->has('DASHY_RESOURCE_UUID'))->toBeTrue();
});

test('a team admin turns the option off from the environment variables page', function (string $resourceType) {
    $user = User::factory()->create();
    $this->team->members()->attach($user, ['role' => 'admin']);
    $this->actingAs($user);
    $resource = match ($resourceType) {
        'application' => dashyDefaultsApplication(),
        'service' => dashyDefaultsService(),
        'database' => dashyDefaultsPostgres(),
    };

    Livewire::test(All::class, ['resource' => $resource])
        ->assertSet('include_dashy_default_variables', true)
        ->set('include_dashy_default_variables', false)
        ->call('saveDashyDefaultVariables');

    $owner = $resource instanceof Application ? $resource->settings->fresh() : $resource->fresh();
    expect($owner->include_dashy_default_variables)->toBeFalse();
})->with(['application', 'service', 'database']);

test('a team member cannot change the option', function () {
    $user = User::factory()->create();
    $this->team->members()->attach($user, ['role' => 'member']);
    $this->actingAs($user);
    $database = dashyDefaultsPostgres();

    Livewire::test(All::class, ['resource' => $database])
        ->set('include_dashy_default_variables', false)
        ->call('saveDashyDefaultVariables');

    expect($database->fresh()->include_dashy_default_variables)->toBeTrue();
});
