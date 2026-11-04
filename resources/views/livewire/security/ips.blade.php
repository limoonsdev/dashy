<div>
    <x-slot:title>Blocked addresses | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Blocked addresses</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                A scanner is blocked for 24 hours on its first hit. If the same address comes back it is blocked
                permanently until you allow it here.
            </p>
        </header>

        @if ($notice)
            <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-[12px]">{{ $notice }}</div>
        @endif

        <x-application.settings-section title="Block an address">
            <div class="grid items-end gap-4 lg:grid-cols-3">
                <x-forms.input wire:model="manualIp" id="manualIp" label="IP address" placeholder="203.0.113.10" />
                <x-forms.input wire:model="manualReason" id="manualReason" label="Reason" />
                <x-forms.button type="button" wire:click="block">Block</x-forms.button>
            </div>
        </x-application.settings-section>

        <x-application.settings-section title="History">
            <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-white/[0.08]">
                <table class="w-full text-left text-[12px]">
                    <thead class="bg-neutral-50 text-[11px] text-neutral-500 dark:bg-white/[0.03] dark:text-fg-faint">
                        <tr>
                            <th class="px-3 py-2 font-medium">Address</th>
                            <th class="px-3 py-2 font-medium">Reason</th>
                            <th class="px-3 py-2 font-medium">Hits</th>
                            <th class="px-3 py-2 font-medium">State</th>
                            <th class="px-3 py-2 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr class="border-t border-neutral-100 dark:border-white/[0.05]">
                                <td class="px-3 py-2 font-mono">{{ $entry->ip }}</td>
                                <td class="px-3 py-2 text-neutral-500 dark:text-fg-dim">{{ $entry->reason }}</td>
                                <td class="px-3 py-2">{{ $entry->hits }}</td>
                                <td class="px-3 py-2">
                                    @if ($entry->approved)
                                        allowed
                                    @elseif ($entry->permanent)
                                        permanent
                                    @elseif ($entry->blocked_until?->isFuture())
                                        until {{ $entry->blocked_until->format('d/m H:i') }}
                                    @else
                                        expired
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="text-[11px] hover:underline" wire:click="approve('{{ $entry->ip }}')">Unban</button>
                                    <span class="px-1 text-neutral-300 dark:text-white/20">·</span>
                                    <button type="button" class="text-[11px] text-error hover:underline" wire:click="remove('{{ $entry->ip }}')">Forget</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-neutral-500 dark:text-fg-dim">No address has been blocked.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-application.settings-section>
    </div>
</div>
