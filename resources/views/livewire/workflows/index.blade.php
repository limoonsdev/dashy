<div>
    <x-slot:title>Workflows | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Workflows</h1>
                <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                    Chain shell commands, HTTP calls and deployments into one runnable sequence.
                </p>
            </div>
            <x-forms.button type="button" wire:click="edit(null)" isHighlighted>New workflow</x-forms.button>
        </header>

        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px]">{{ $error }}</div>
        @endif
        @if ($notice)
            <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-[12px]">{{ $notice }}</div>
        @endif

        <x-application.settings-section title="Saved workflows">
            <div class="flex flex-col divide-y divide-neutral-100 dark:divide-white/[0.05]">
                @forelse ($workflows as $workflow)
                    <div class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold">{{ $workflow->name }}</p>
                            <p class="truncate text-[12px] text-neutral-500 dark:text-fg-dim">
                                {{ count($workflow->stepList()) }} step(s) · {{ $workflow->trigger }}
                                @if ($workflow->last_run_at) · last run {{ $workflow->last_run_at->diffForHumans() }} ({{ $workflow->last_status }}) @endif
                            </p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[10px] uppercase {{ $workflow->enabled ? 'bg-success/15 text-success' : 'bg-neutral-200 text-neutral-600 dark:bg-white/10 dark:text-fg-dim' }}">
                            {{ $workflow->enabled ? 'enabled' : 'paused' }}
                        </span>
                        <button type="button" class="text-[11px] hover:underline" wire:click="run('{{ $workflow->uuid }}')">Run</button>
                        <button type="button" class="text-[11px] hover:underline" wire:click="edit('{{ $workflow->uuid }}')">Edit</button>
                        <button type="button" class="text-[11px] hover:underline" wire:click="toggle('{{ $workflow->uuid }}')">
                            {{ $workflow->enabled ? 'Pause' : 'Enable' }}
                        </button>
                        <button type="button" class="text-[11px] text-error hover:underline"
                            wire:click="remove('{{ $workflow->uuid }}')"
                            wire:confirm="Delete this workflow?">Delete</button>
                    </div>
                @empty
                    <p class="py-4 text-[13px] text-neutral-500 dark:text-fg-dim">No workflows yet.</p>
                @endforelse
            </div>
        </x-application.settings-section>

        <x-application.settings-section :title="$editing ? 'Edit workflow' : 'New workflow'">
            <div class="grid gap-4 lg:grid-cols-2">
                <x-forms.input wire:model="name" id="name" label="Name" placeholder="Nightly backup check" />
                <x-forms.input wire:model="description" id="description" label="Description" placeholder="What this workflow is for" />
                <div>
                    <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Trigger</label>
                    <select wire:model="trigger" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                        <option value="manual">Manual</option>
                        <option value="schedule">Schedule</option>
                        <option value="webhook">Webhook</option>
                    </select>
                </div>
                <x-forms.input wire:model="cron" id="cron" label="Cron expression" placeholder="0 3 * * *" />
            </div>

            <label class="mt-4 flex items-center gap-2 text-[13px]">
                <input type="checkbox" wire:model="enabled" class="rounded border-neutral-300 dark:border-white/20">
                Enabled
            </label>

            <div class="mt-4">
                <label class="text-[12px] text-neutral-500 dark:text-fg-faint">
                    Steps (JSON). Types: {{ implode(', ', array_keys($stepTypes)) }}
                </label>
                <textarea wire:model="steps" rows="12" spellcheck="false"
                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-white p-3 font-mono text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]"></textarea>
                @error('steps') <p class="mt-1 text-[12px] text-error">{{ $message }}</p> @enderror
            </div>

            <div class="mt-4 flex gap-2">
                <x-forms.button type="button" wire:click="save" isHighlighted>Save workflow</x-forms.button>
            </div>

            @if ($results !== [])
                <div class="mt-4 flex flex-col gap-2">
                    @foreach ($results as $result)
                        <div class="rounded-lg border p-2 text-[12px] {{ $result['ok'] ? 'border-neutral-200 dark:border-white/[0.08]' : 'border-error/40 bg-error/10' }}">
                            <span class="font-semibold">#{{ $result['index'] + 1 }} {{ $result['type'] }}</span>
                            <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap font-mono text-[11px]">{{ $result['output'] }}</pre>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-application.settings-section>
    </div>
</div>
