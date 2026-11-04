<?php

namespace App\Livewire\Modules;

use App\Models\ModuleRecord;
use Illuminate\Support\Str;
use Livewire\Component;

class Index extends Component
{
    public string $module = 'calendar';

    public ?string $editing = null;

    public string $title = '';

    public string $body = '';

    public string $date = '';

    public string $recipients = '';

    public function mount(string $module = 'calendar'): void
    {
        $this->module = array_key_exists($module, ModuleRecord::MODULES) ? $module : 'calendar';
    }

    public function edit(?string $uuid = null): void
    {
        $this->resetValidation();

        if ($uuid === null) {
            $this->editing = null;
            $this->title = $this->body = $this->date = $this->recipients = '';

            return;
        }

        $record = $this->find($uuid);
        $this->editing = $record->uuid;
        $this->title = $record->title;
        $this->body = (string) ($record->payload['body'] ?? '');
        $this->date = (string) ($record->payload['start'] ?? '');
        $this->recipients = implode(', ', (array) ($record->payload['recipients'] ?? []));
    }

    public function save(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:20000'],
            'date' => ['nullable', 'string', 'max:32'],
            'recipients' => ['nullable', 'string', 'max:1000'],
        ]);

        $record = $this->editing !== null ? $this->find($this->editing) : new ModuleRecord([
            'team_id' => currentTeam()->id,
            'module' => $this->module,
        ]);

        $record->fill([
            'title' => $this->title,
            'status' => $record->status ?: 'draft',
            'payload' => [
                'body' => $this->body,
                'start' => $this->date,
                'recipients' => array_values(array_filter(array_map('trim', explode(',', $this->recipients)))),
            ],
        ]);
        $record->team_id = currentTeam()->id;
        $record->module = $this->module;
        $record->save();

        $this->editing = $record->uuid;
        $this->dispatch('success', ModuleRecord::MODULES[$this->module].' saved!');
    }

    public function setStatus(string $uuid, string $status): void
    {
        if (! in_array($status, ['draft', 'sent', 'signed', 'published', 'archived'], true)) {
            return;
        }

        $this->find($uuid)->update(['status' => $status]);
    }

    public function remove(string $uuid): void
    {
        $this->find($uuid)->delete();
        $this->editing = null;
    }

    public function shareLink(string $uuid): string
    {
        $record = $this->find($uuid);
        $record->forceFill(['payload' => array_merge((array) $record->payload, ['share_token' => $record->payload['share_token'] ?? Str::random(40)])])->save();

        return url('/share/'.$record->payload['share_token']);
    }

    public function render()
    {
        return view('livewire.modules.index', [
            'records' => ModuleRecord::query()
                ->where('team_id', currentTeam()->id)
                ->where('module', $this->module)
                ->latest()
                ->get(),
            'modules' => ModuleRecord::MODULES,
        ]);
    }

    private function find(string $uuid): ModuleRecord
    {
        return ModuleRecord::query()->where('team_id', currentTeam()->id)->where('uuid', $uuid)->firstOrFail();
    }
}
