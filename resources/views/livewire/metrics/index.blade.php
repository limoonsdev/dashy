<div class="flex w-full min-w-0 flex-col gap-5">
    <x-slot:title>Metrics | Dashy</x-slot>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="min-w-0 text-[24px]! leading-7! font-semibold! tracking-tight!">Metrics</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Resource usage across every server — history, and what is happening right now.
            </p>
        </div>

        <div class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-neutral-200 bg-neutral-50 p-0.5 dark:border-white/[0.08] dark:bg-white/[0.02]">
            <button type="button" wire:click="show('fleet')"
                @class([
                    'inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-[12px] font-medium transition-colors',
                    'bg-white text-black shadow-sm ring-1 ring-neutral-200 dark:bg-white/[0.09] dark:text-fg dark:ring-white/[0.08]' => $tab === 'fleet',
                    'text-neutral-500 hover:text-black dark:text-fg-faint dark:hover:text-fg' => $tab !== 'fleet',
                ])>
                <x-reicon name="graph" class="size-3.5" />
                Fleet history
            </button>
            <button type="button" wire:click="show('live')"
                @class([
                    'inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-[12px] font-medium transition-colors',
                    'bg-white text-black shadow-sm ring-1 ring-neutral-200 dark:bg-white/[0.09] dark:text-fg dark:ring-white/[0.08]' => $tab === 'live',
                    'text-neutral-500 hover:text-black dark:text-fg-faint dark:hover:text-fg' => $tab !== 'live',
                ])>
                <x-reicon name="refresh3" class="size-3.5" />
                Live
                <span class="ml-0.5 inline-flex size-1.5 animate-pulse rounded-full bg-emerald-500"></span>
            </button>
        </div>
    </div>

    <div wire:loading.class="opacity-60" wire:target="show">
        @if ($tab === 'fleet')
            @livewire('fleet.metrics')
        @else
            @livewire('live-metrics')
        @endif
    </div>
</div>
