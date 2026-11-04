<?php

namespace App\Livewire\Settings;

use App\Jobs\CheckForUpdatesJob;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Services\UpdateChecker;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Updates extends Component
{
    use AuthorizesRequests;

    public InstanceSettings $settings;

    public ?Server $server = null;

    #[Validate('string')]
    public string $auto_update_frequency;

    #[Validate('string|required')]
    public string $update_check_frequency;

    #[Validate('boolean')]
    public bool $is_auto_update_enabled;

    #[Validate('required|string|in:docker.io,ghcr.io')]
    public string $docker_registry_url;

    #[Validate('required|string|in:stable,preview,nightly,custom')]
    public string $update_channel = 'stable';

    #[Validate('nullable|required_if:update_channel,custom|string|max:128|regex:/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/')]
    public ?string $update_custom_tag = null;

    /** @var array<string, mixed> */
    public array $check = [];

    public function mount()
    {
        if (! isInstanceAdmin()) {
            return redirect()->route('dashboard');
        }
        if (! isCloud()) {
            $this->server = Server::findOrFail(0);
        }

        $this->settings = instanceSettings();

        // Older rows can carry nulls in any of these columns; the properties are
        // typed, so normalise everything before assigning it.
        $this->auto_update_frequency = (string) ($this->settings->auto_update_frequency ?: InstanceSettings::randomAutoUpdateFrequency());
        $this->update_check_frequency = (string) ($this->settings->update_check_frequency ?: '0 * * * *');
        $this->is_auto_update_enabled = (bool) $this->settings->is_auto_update_enabled;
        $this->docker_registry_url = (string) ($this->settings->docker_registry_url ?: 'docker.io');

        $channel = (string) ($this->settings->update_channel ?: 'stable');
        $this->update_channel = in_array($channel, ['stable', 'preview', 'nightly', 'custom'], true) ? $channel : 'stable';
        $this->update_custom_tag = $this->settings->update_custom_tag !== null
            ? (string) $this->settings->update_custom_tag
            : null;
        $this->check = UpdateChecker::run();
    }

    public function instantSave()
    {
        try {
            $this->authorize('update', $this->settings);
            if ($this->settings->is_auto_update_enabled === true) {
                $this->validate([
                    'auto_update_frequency' => ['required', 'string'],
                ]);
            }
            $validated = $this->validate([
                'docker_registry_url' => ['required', 'string', 'in:docker.io,ghcr.io'],
                'update_channel' => ['required', 'string', 'in:stable,preview,nightly,custom'],
                'update_custom_tag' => ['nullable', 'required_if:update_channel,custom', 'string', 'max:128', 'regex:/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/'],
            ]);
            $this->settings->auto_update_frequency = $this->auto_update_frequency;
            $this->settings->update_check_frequency = $this->update_check_frequency;
            $this->settings->is_auto_update_enabled = $this->is_auto_update_enabled;
            $this->settings->docker_registry_url = $validated['docker_registry_url'];
            $this->settings->switchUpdateChannel($validated['update_channel']);
            $this->settings->update_custom_tag = $validated['update_custom_tag'];
            $this->syncRegistryUrlToEnv($validated['docker_registry_url']);
            $channelChanged = $this->settings->isDirty(['update_channel', 'update_custom_tag']);
            $changedFields = auditChangedFields($this->settings);
            $this->settings->save();
            if ($channelChanged) {
                invalidate_versions_cache();
                (new CheckForUpdatesJob(cdnTries: 1))->handle();
                $this->dispatch('updateAvailable');
            }
            if ($changedFields !== []) {
                auditLog('ui.instance.settings.updated', [
                    'team_id' => null,
                    'resource' => 'instance',
                    'section' => 'updates',
                    'changed_fields' => $changedFields,
                ]);
            }
            $this->dispatch('success', 'Settings updated!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return handleError($e, $this);
        }
    }

    protected function syncRegistryUrlToEnv(string $registryUrl): void
    {
        if (! $this->server) {
            return;
        }

        try {
            instant_remote_process($this->registryEnvSyncCommand($registryUrl), $this->server);
        } catch (\Exception $e) {
            Log::warning('Failed to sync REGISTRY_URL to .env', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to sync REGISTRY_URL to .env. Settings were not saved.', previous: $e);
        }
    }

    /**
     * One command per line: the non-root sudo parser adds sudo to line starts, not to `then`/`else` branches,
     * and a `>>` redirect would be opened by the SSH user.
     *
     * @return list<string>
     */
    private function registryEnvSyncCommand(string $registryUrl): array
    {
        $envFile = '/data/dashy/source/.env';
        $sedExpression = escapeshellarg("s|^REGISTRY_URL=.*|REGISTRY_URL={$registryUrl}|");
        $registryLine = escapeshellarg("REGISTRY_URL={$registryUrl}");

        return [
            "if grep -q '^REGISTRY_URL=' {$envFile}; then",
            "    sed -i {$sedExpression} {$envFile}",
            'else',
            "    printf '%s\\n' {$registryLine} | tee -a {$envFile} > /dev/null",
            'fi',
        ];
    }

    public function submit()
    {
        try {
            $this->authorize('update', $this->settings);
            $this->resetErrorBag();
            $this->validate();

            if ($this->is_auto_update_enabled && ! validate_cron_expression($this->auto_update_frequency)) {
                $this->dispatch('error', 'Invalid Cron / Human expression for Auto Update Frequency.');
                if (empty($this->auto_update_frequency)) {
                    $this->auto_update_frequency = InstanceSettings::randomAutoUpdateFrequency();
                }

                return;
            }

            if (! validate_cron_expression($this->update_check_frequency)) {
                $this->dispatch('error', 'Invalid Cron / Human expression for Update Check Frequency.');
                if (empty($this->update_check_frequency)) {
                    $this->update_check_frequency = '0 * * * *';
                }

                return;
            }

            $this->instantSave();
            if ($this->server) {
                $this->server->setupDynamicProxyConfiguration();
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return handleError($e, $this);
        }
    }

    public function checkManually()
    {
        try {
            $this->authorize('update', $this->settings);

            // GitHub is the source of truth for this fork: the CDN that published
            // versions.json no longer answers, so a CDN-only check always failed here.
            $this->check = UpdateChecker::run(fresh: true);
            $this->dispatch('updateAvailable');

            // A source build follows commits, so a release tag alone is not enough:
            // without the branch head the instance cannot know whether it is behind.
            $reachable = ($this->check['current_commit'] ?? null) !== null
                ? ($this->check['latest_commit'] ?? null) !== null
                : ($this->check['latest_tag'] ?? null) !== null || ($this->check['latest_commit'] ?? null) !== null;

            if (! $reachable) {
                $this->dispatch('error', 'Could not reach GitHub. '.($this->check['error'] ?? 'Try again in a moment.'));

                return;
            }

            $this->dispatch('success', $this->check['available']
                ? 'Update available: '.($this->check['latest'] ?? 'unknown')
                : 'This instance is up to date ('.($this->check['current'] ?? config('constants.dashy.version')).').');
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function render()
    {
        return view('livewire.settings.updates');
    }
}
