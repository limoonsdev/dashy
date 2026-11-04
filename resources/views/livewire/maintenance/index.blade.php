<div>
    <x-slot:title>Clean-up | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Intelligent clean-up</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Frees disk space without touching running services: only unused Docker objects older than a
                week, rotated logs, package caches and old temp files are removed.
            </p>
        </header>

        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px]">{{ $error }}</div>
        @endif
        @if ($notice)
            <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-[12px]">{{ $notice }}</div>
        @endif

        <x-application.settings-section title="Schedule">
            <div class="grid gap-4 lg:grid-cols-4">
                <label class="flex items-center gap-2 text-[13px]">
                    <input type="checkbox" wire:model="dailyEnabled" class="rounded border-neutral-300 dark:border-white/20">
                    Daily
                </label>
                <x-forms.input wire:model="dailyTime" id="dailyTime" type="time" label="Daily time" />
                <label class="flex items-center gap-2 text-[13px]">
                    <input type="checkbox" wire:model="weeklyEnabled" class="rounded border-neutral-300 dark:border-white/20">
                    Weekly
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Day</label>
                        <select wire:model="weeklyDay" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                            @foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                                <option value="{{ $day }}">{{ ucfirst($day) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-forms.input wire:model="weeklyTime" id="weeklyTime" type="time" label="Time" />
                </div>
            </div>
            <p class="mt-3 text-[12px] text-neutral-500 dark:text-fg-dim">
                The scheduler runs hourly and picks up whichever window is due.
            </p>
        </x-application.settings-section>

        <x-application.settings-section title="Tasks">
            <div class="flex flex-col gap-2">
                @foreach ($available as $key => $label)
                    <label class="flex items-start gap-2 text-[12px]">
                        <input type="checkbox" wire:model="options" value="{{ $key }}"
                            class="mt-0.5 rounded border-neutral-300 dark:border-white/20">
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="mt-4 grid items-end gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Server</label>
                    <select wire:model="serverUuid" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                        @foreach ($servers as $server)
                            <option value="{{ $server->uuid }}">{{ $server->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-forms.button type="button" wire:click="runNow">Run now</x-forms.button>
            </div>

            <div class="mt-3 flex gap-2">
                <x-forms.button type="button" wire:click="save" isHighlighted>Save schedule</x-forms.button>
            </div>

            @if ($results !== [])
                <div class="mt-4 flex flex-col gap-2">
                    @foreach ($results as $result)
                        <div class="rounded-lg border p-2 text-[12px] {{ $result['ok'] ? 'border-neutral-200 dark:border-white/[0.08]' : 'border-error/40 bg-error/10' }}">
                            <span class="font-semibold">{{ $result['option'] }}</span> — {{ $result['ok'] ? 'done' : 'failed' }}
                            <pre class="mt-1 max-h-32 overflow-auto whitespace-pre-wrap font-mono text-[11px]">{{ $result['output'] }}</pre>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-application.settings-section>
    </div>
</div>
