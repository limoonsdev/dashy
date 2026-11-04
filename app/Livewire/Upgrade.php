<?php

namespace App\Livewire;

use App\Actions\Server\UpdateDashy;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Services\DashySourceRelease;
use App\Services\DashyUpgradeStatus;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Upgrade extends Component
{
    public bool $updateInProgress = false;

    public bool $isUpgradeAvailable = false;

    public string $latestVersion = '';

    public string $currentVersion = '';

    public bool $devMode = false;

    public bool $fullButton = false;

    /** Render nothing when the instance is up to date (the dashboard uses this). */
    public bool $quiet = false;

    protected $listeners = ['updateAvailable' => 'checkUpdate'];

    public function mount()
    {
        $this->refreshUpgradeState();
    }

    public function checkUpdate()
    {
        try {
            $this->refreshUpgradeState(fresh: true);
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    protected function refreshUpgradeState(bool $fresh = false): void
    {
        $this->currentVersion = config('constants.dashy.version');
        $this->latestVersion = get_latest_version_of_dashy($fresh);
        $this->devMode = isDev();

        if ($this->devMode) {
            $this->isUpgradeAvailable = true;

            return;
        }

        // An instance installed by the one-line installer runs its own build of the
        // source repository (version "local-<commit>"), which the CDN knows nothing about.
        if (DashySourceRelease::isSourceBuild()) {
            $latest = DashySourceRelease::latest($fresh);
            $this->latestVersion = $latest !== null ? 'local-'.$latest['short'] : $this->currentVersion;
            $this->isUpgradeAvailable = DashySourceRelease::updateAvailable($fresh);

            return;
        }

        $settings = InstanceSettings::find(0);
        if (data_get($settings, 'update_channel') === 'custom') {
            $this->latestVersion = (string) $settings->update_custom_tag;
            $this->isUpgradeAvailable = $this->fullButton && $this->latestVersion !== '';

            return;
        }

        $hasNewerVersion = version_compare($this->latestVersion, $this->currentVersion, '>');
        $newVersionAvailable = (bool) data_get($settings, 'new_version_available', false);

        if ($settings && $newVersionAvailable && ! $hasNewerVersion) {
            $settings->update(['new_version_available' => false]);
        }

        // A newer release is enough: new_version_available is only written by the CDN
        // check job, which cannot answer for this fork, and gating on it hid every update.
        $this->isUpgradeAvailable = $hasNewerVersion;
    }

    public function upgrade()
    {
        try {
            if (! isInstanceAdmin()) {
                abort(403);
            }
            if ($this->updateInProgress) {
                return;
            }
            $this->updateInProgress = true;
            $this->writeStartingStatus();
            Cache::forget('dashy:upgrade:status');
            dispatch(function () {
                try {
                    UpdateDashy::run(manual_update: true);
                } catch (\Throwable $e) {
                    report($e);
                    // Without this line the progress view spins until the status file
                    // goes stale (ten minutes) even though the update already failed.
                    $this->writeErrorStatus($e->getMessage());
                    Cache::forget('dashy:upgrade:status');
                }
            })->afterResponse();

            auditLog('ui.instance.upgrade_started', [
                'team_id' => null,
                'resource' => 'instance',
                'from_version' => config('constants.dashy.version'),
                'to_version' => $this->latestVersion,
            ]);
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function getUpgradeStatus(): array
    {
        // Only root team members can view upgrade status
        if (auth()->user()?->currentTeam()?->id !== 0) {
            return ['status' => 'none'];
        }

        $server = Server::find(0);
        if (! $server) {
            return ['status' => 'none'];
        }

        $statusFile = DashyUpgradeStatus::FILE;

        // The progress view polls every couple of seconds; one SSH round trip per poll
        // made the page feel stuck. Cache the read for a moment and cap the wait.
        try {
            $content = Cache::remember('dashy:upgrade:status', now()->addSeconds(2), function () use ($server, $statusFile) {
                return trim((string) instant_remote_process(
                    ["cat {$statusFile} 2>/dev/null || echo ''"],
                    $server,
                    false,
                    false,
                    10,
                ));
            });
        } catch (\Throwable $e) {
            return ['status' => 'none'];
        }

        return DashyUpgradeStatus::fromFile(
            content: (string) $content,
            // The version of the container that answers, not $currentVersion: after the restart, that is still the old version of the page.
            runningVersion: (string) config('constants.dashy.version'),
            targetVersion: $this->latestVersion !== '' ? $this->latestVersion : get_latest_version_of_dashy(),
        );
    }

    /**
     * Surfaces a failed update in the progress view instead of leaving it pending.
     */
    private function writeErrorStatus(string $message): void
    {
        $server = Server::find(0);

        if (! $server) {
            return;
        }

        $clean = mb_substr(preg_replace('/\s+/', ' ', $message) ?? 'Update failed', 0, 200);
        $line = 'error|'.$clean.'|'.now()->toIso8601String();

        try {
            instant_remote_process([
                'printf %s '.escapeshellarg($line).' > '.DashyUpgradeStatus::FILE.' 2>/dev/null || true',
            ], $server, false, false, 10);
        } catch (\Throwable) {
            // Nothing else to do: the original error is already in the log.
        }
    }

    /**
     * Writes the first progress line before the update script starts, so the UI
     * shows "preparing" immediately instead of waiting for the remote script.
     */
    private function writeStartingStatus(): void
    {
        $server = Server::find(0);

        if (! $server) {
            return;
        }

        $line = '1|Preparing the update|'.now()->toIso8601String();

        try {
            instant_remote_process([
                'printf %s '.escapeshellarg($line).' > '.DashyUpgradeStatus::FILE.' 2>/dev/null || true',
            ], $server, false, false, 10);
        } catch (\Throwable) {
            // A missing status file only costs the first progress line.
        }
    }
}
