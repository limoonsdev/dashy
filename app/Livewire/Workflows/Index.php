<?php

namespace App\Livewire\Workflows;

use App\Models\Workflow;
use App\Services\WorkflowRunner;
use Livewire\Component;

class Index extends Component
{
    public ?string $editing = null;

    public string $name = '';

    public string $description = '';

    public bool $enabled = true;

    public string $trigger = 'manual';

    public string $cron = '';

    public string $steps = '[]';

    public ?string $error = null;

    public ?string $notice = null;

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public function edit(?string $uuid = null): void
    {
        $this->error = $this->notice = null;
        $this->results = [];

        if ($uuid === null) {
            $this->editing = null;
            $this->name = $this->description = $this->cron = '';
            $this->enabled = true;
            $this->trigger = 'manual';
            $this->steps = json_encode([
                ['type' => 'command', 'server' => '', 'command' => 'docker ps'],
            ], JSON_PRETTY_PRINT);

            return;
        }

        $workflow = $this->find($uuid);
        $this->editing = $workflow->uuid;
        $this->name = $workflow->name;
        $this->description = (string) $workflow->description;
        $this->enabled = (bool) $workflow->enabled;
        $this->trigger = (string) $workflow->trigger;
        $this->cron = (string) $workflow->cron;
        $this->steps = json_encode($workflow->stepList(), JSON_PRETTY_PRINT);
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'trigger' => ['required', 'string', 'in:manual,schedule,webhook'],
            'cron' => ['nullable', 'string', 'max:120'],
            'steps' => ['required', 'string'],
        ]);

        $decoded = json_decode($this->steps, true);

        if (! is_array($decoded)) {
            $this->addError('steps', 'Steps must be a JSON array.');

            return;
        }

        foreach ($decoded as $index => $step) {
            if (! is_array($step) || ! isset(Workflow::STEP_TYPES[$step['type'] ?? ''])) {
                $this->addError('steps', 'Step '.($index + 1).' has an unknown type.');

                return;
            }
        }

        $workflow = $this->editing !== null ? $this->find($this->editing) : new Workflow(['team_id' => currentTeam()->id]);
        $workflow->fill([
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'trigger' => $this->trigger,
            'cron' => $this->cron !== '' ? $this->cron : null,
            'steps' => array_values($decoded),
        ]);
        $workflow->team_id = currentTeam()->id;
        $workflow->save();

        $this->editing = $workflow->uuid;
        $this->notice = 'Workflow saved.';
        $this->dispatch('success', 'Workflow saved!');
    }

    public function run(string $uuid): void
    {
        $workflow = $this->find($uuid);
        $this->error = $this->notice = null;

        try {
            $outcome = (new WorkflowRunner)->handle($workflow);
            $this->results = $outcome['results'];
            $this->notice = 'Workflow finished with status: '.$outcome['status'].'.';
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function toggle(string $uuid): void
    {
        $workflow = $this->find($uuid);
        $workflow->update(['enabled' => ! $workflow->enabled]);
    }

    public function remove(string $uuid): void
    {
        $this->find($uuid)->delete();
        $this->editing = null;
        $this->notice = 'Workflow deleted.';
    }

    public function render()
    {
        return view('livewire.workflows.index', [
            'workflows' => Workflow::query()->where('team_id', currentTeam()->id)->latest()->get(),
            'stepTypes' => Workflow::STEP_TYPES,
        ]);
    }

    private function find(string $uuid): Workflow
    {
        return Workflow::query()->where('team_id', currentTeam()->id)->where('uuid', $uuid)->firstOrFail();
    }
}
