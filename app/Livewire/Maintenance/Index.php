<?php

namespace App\Livewire\Maintenance;

use App\Models\MaintenanceSetting;
use App\Models\Server;
use App\Services\Maintenance\MaintenanceCleaner;
use Livewire\Component;

class Index extends Component
{
    public bool $dailyEnabled = false;

    public string $dailyTime = '03:30';

    public bool $weeklyEnabled = false;

    public string $weeklyDay = 'sunday';

    public string $weeklyTime = '04:30';

    /** @var array<int, string> */
    public array $options = [];

    public string $serverUuid = '';

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(): void
    {
        $settings = MaintenanceSetting::forTeam(currentTeam()->id);
        $this->dailyEnabled = $settings->daily_enabled;
        $this->dailyTime = $settings->daily_time;
        $this->weeklyEnabled = $settings->weekly_enabled;
        $this->weeklyDay = $settings->weekly_day;
        $this->weeklyTime = $settings->weekly_time;
        $this->options = (array) $settings->options;
        $this->serverUuid = (string) (Server::query()->where('team_id', currentTeam()->id)->value('uuid') ?? '');
    }

    public function save(): void
    {
        $this->validate([
            'dailyTime' => ['required', 'date_format:H:i'],
            'weeklyTime' => ['required', 'date_format:H:i'],
            'weeklyDay' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'options' => ['array'],
        ]);

        MaintenanceSetting::forTeam(currentTeam()->id)->update([
            'daily_enabled' => $this->dailyEnabled,
            'daily_time' => $this->dailyTime,
            'weekly_enabled' => $this->weeklyEnabled,
            'weekly_day' => $this->weeklyDay,
            'weekly_time' => $this->weeklyTime,
            'options' => array_values(array_intersect($this->options, array_keys(MaintenanceSetting::OPTIONS))),
        ]);

        $this->notice = 'Clean-up schedule saved.';
    }

    public function runNow(): void
    {
        $this->error = null;
        $this->notice = null;
        $this->results = [];

        $server = Server::query()->where('team_id', currentTeam()->id)->where('uuid', $this->serverUuid)->first();

        if ($server === null) {
            $this->error = 'Choose a server first.';

            return;
        }

        if ($this->options === []) {
            $this->error = 'Select at least one clean-up task.';

            return;
        }

        try {
            $this->results = (new MaintenanceCleaner)->run($server, $this->options);
            $this->notice = 'Clean-up finished on '.$server->name.'.';
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.maintenance.index', [
            'servers' => Server::query()->where('team_id', currentTeam()->id)->get(),
            'available' => MaintenanceSetting::OPTIONS,
        ]);
    }
}
