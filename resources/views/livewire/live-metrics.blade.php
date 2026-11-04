<div wire:poll.5s="poll">
    <x-slot:title>Live metrics | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Live metrics</h1>
                <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                    CPU, memory and disk as reported by each server, refreshed every 5 seconds.
                </p>
            </div>
            <button type="button" class="button h-8! px-3! text-[12px]!" wire:click="poll">Refresh now</button>
        </header>

        @if ($rows === [])
            <p class="text-[13px] text-neutral-500 dark:text-fg-dim">Add a server to see live numbers.</p>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($rows as $row)
                <div class="application-settings-section">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-[14px] font-semibold">{{ $row['name'] }}</p>
                            <p class="text-[11px] text-neutral-500 dark:text-fg-dim">
                                {{ $row['cores'] }} vCPU · load {{ $row['load'] }} · {{ $row['uptime'] }}
                            </p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[10px] uppercase {{ $row['reachable'] ? 'bg-success/15 text-success' : 'bg-error/15 text-error' }}">
                            {{ $row['reachable'] ? 'online' : 'unreachable' }}
                        </span>
                    </div>

                    @php
                        $points = collect($history[$row['uuid']] ?? [])->values();
                        $polyline = $points->count() > 1
                            ? $points->map(fn ($value, $index) => round($index / ($points->count() - 1) * 100, 2).','.round(30 - min(100, $value) / 100 * 28, 2))->implode(' ')
                            : '0,29 100,29';
                    @endphp

                    <div class="mt-4 text-dashy dark:text-brand-light">
                        <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="h-16 w-full" aria-hidden="true">
                            <polyline points="{{ $polyline }}" fill="none" stroke="currentColor" stroke-width="1.4" vector-effect="non-scaling-stroke" />
                        </svg>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-3 text-center">
                        @foreach ([['CPU', $row['cpu'], '%'], ['RAM', $row['memory'], '%'], ['Disk', $row['disk'], '%']] as [$label, $value, $unit])
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-neutral-500 dark:text-fg-faint">{{ $label }}</p>
                                <p class="text-[18px] font-semibold">{{ $value }}{{ $unit }}</p>
                                <div class="mt-1 h-1 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-white/10">
                                    <div class="h-full rounded-full {{ $value > 85 ? 'bg-error' : ($value > 65 ? 'bg-warning' : 'bg-success') }}"
                                        style="width: {{ min(100, max(0, (float) $value)) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
