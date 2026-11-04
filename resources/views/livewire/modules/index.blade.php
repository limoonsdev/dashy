<div>
    <x-slot:title>Workspace | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Workspace modules</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Four small apps that share the same team, sidebar and permissions.
            </p>
        </header>

        <div class="flex flex-wrap gap-2" role="tablist">
            @foreach ($modules as $key => $label)
                <a href="{{ route('modules.index', $key) }}" role="tab"
                    aria-selected="{{ $module === $key ? 'true' : 'false' }}"
                    class="button h-8! px-3! text-[12px]! {{ $module === $key ? 'button-highlighted' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <x-application.settings-section title="{{ $editing ? 'Edit' : 'New' }} record">
                <div class="flex flex-col gap-3">
                    <x-forms.input wire:model="title" id="title" label="Title"
                        placeholder="{{ $module === 'calendar' ? 'Sprint planning' : 'Untitled' }}" />
                    @if ($module === 'calendar')
                        <x-forms.input wire:model="date" id="date" type="datetime-local" label="Starts at" />
                    @endif
                    @if ($module === 'sign')
                        <x-forms.input wire:model="recipients" id="recipients" label="Signers"
                            placeholder="ada@example.com, grace@example.com" />
                    @endif
                    <div>
                        <label class="text-[12px] text-neutral-500 dark:text-fg-faint">
                            {{ $module === 'design' ? 'Shapes (JSON)' : 'Content' }}
                        </label>
                        <textarea wire:model="body" rows="10"
                            class="mt-1 w-full rounded-lg border border-neutral-200 bg-white p-3 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]"
                            placeholder="{{ $module === 'design' ? '[{"type":"rect","x":40,"y":40,"w":240,"h":120}]' : 'Write here…' }}"></textarea>
                    </div>
                    <div class="flex gap-2">
                        <x-forms.button type="button" wire:click="save" isHighlighted>Save</x-forms.button>
                        <button type="button" class="button h-8! px-3! text-[12px]!" wire:click="edit(null)">New</button>
                    </div>
                </div>
            </x-application.settings-section>

            <x-application.settings-section title="{{ $modules[$module] }}">
                <div class="flex flex-col divide-y divide-neutral-100 dark:divide-white/[0.05]">
                    @forelse ($records as $record)
                        <div class="flex flex-wrap items-center gap-2 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-semibold">{{ $record->title }}</p>
                                <p class="text-[11px] text-neutral-500 dark:text-fg-dim">
                                    {{ $record->status }} · {{ $record->updated_at->diffForHumans() }}
                                    @if (! empty($record->payload['start'])) · {{ $record->payload['start'] }} @endif
                                </p>
                            </div>
                            <button type="button" class="text-[11px] hover:underline" wire:click="edit('{{ $record->uuid }}')">Edit</button>
                            @if ($module === 'sign')
                                <button type="button" class="text-[11px] hover:underline" wire:click="setStatus('{{ $record->uuid }}', 'sent')">Send</button>
                                <button type="button" class="text-[11px] hover:underline" wire:click="setStatus('{{ $record->uuid }}', 'signed')">Mark signed</button>
                            @endif
                            @if ($module === 'doc' || $module === 'design')
                                <button type="button" class="text-[11px] hover:underline" wire:click="setStatus('{{ $record->uuid }}', 'published')">Publish</button>
                            @endif                            <button type="button" class="text-[11px] hover:underline" wire:click="share('{{ $record->uuid }}')">Share</button>
                            <button type="button" class="text-[11px] text-error hover:underline"
                                wire:click="remove('{{ $record->uuid }}')"
                                wire:confirm="Delete this record?">Delete</button>
                        </div>
                    @empty
                        <p class="py-4 text-[13px] text-neutral-500 dark:text-fg-dim">Nothing here yet.</p>
                    @endforelse
                </div>

                @if ($shareUrl)
                    <div class="mt-4 flex gap-2">
                        <input readonly value="{{ $shareUrl }}" class="input min-w-0 flex-1 font-mono text-[12px]">
                        <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="button h-8! px-3! text-[12px]!">Open</a>
                    </div>
                    <p class="mt-1 text-[11px] text-neutral-500 dark:text-fg-dim">Anyone with this link can read the record.</p>
                @endif
            </x-application.settings-section>
        </div>
    </div>
</div>
