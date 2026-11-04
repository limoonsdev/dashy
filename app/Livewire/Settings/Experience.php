<?php

namespace App\Livewire\Settings;

use Livewire\Component;

class Experience extends Component
{
    public bool $bootScreen = true;

    public bool $cliPairing = true;

    public int $metricsRefresh = 15;

    public int $metricsRetention = 30;

    public function mount(): void
    {
        abort_unless(isInstanceAdmin(), 403);

        $settings = instanceSettings();

        $this->bootScreen = (bool) ($settings->is_boot_screen_enabled ?? true);
        $this->cliPairing = (bool) ($settings->is_cli_pairing_enabled ?? true);
        $this->metricsRefresh = (int) ($settings->metrics_refresh_seconds ?? 15);
        $this->metricsRetention = (int) ($settings->metrics_retention_days ?? 30);
    }

    public function save(): void
    {
        $this->validate([
            'bootScreen' => ['boolean'],
            'cliPairing' => ['boolean'],
            'metricsRefresh' => ['required', 'integer', 'between:5,120'],
            'metricsRetention' => ['required', 'integer', 'between:1,365'],
        ]);

        $this->authorize('update', instanceSettings());

        instanceSettings()->update([
            'is_boot_screen_enabled' => $this->bootScreen,
            'is_cli_pairing_enabled' => $this->cliPairing,
            'metrics_refresh_seconds' => $this->metricsRefresh,
            'metrics_retention_days' => $this->metricsRetention,
        ]);

        auditLog('ui.settings.experience.updated', [
            'resource' => 'instance_settings',
            'boot_screen' => $this->bootScreen,
            'cli_pairing' => $this->cliPairing,
        ]);

        $this->dispatch('success', 'Experience settings saved.');
    }

    public function render()
    {
        return view('livewire.settings.experience');
    }
}
