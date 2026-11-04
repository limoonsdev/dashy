<?php

namespace App\Livewire\Metrics;

use Livewire\Component;

/**
 * One Monitoring entry: fleet metrics (Sentinel history) and live metrics
 * (polled from the servers) are two tabs of the same page instead of two
 * unrelated menu entries.
 */
class Index extends Component
{
    public string $tab = 'fleet';

    public function show(string $tab): void
    {
        if (! in_array($tab, ['fleet', 'live'], true)) {
            return;
        }

        $this->tab = $tab;
    }

    public function render()
    {
        return view('livewire.metrics.index');
    }
}
