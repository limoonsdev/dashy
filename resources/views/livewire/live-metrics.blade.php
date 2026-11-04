<div wire:poll.5s="poll">
    <x-slot:title>Live metrics | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Live metrics</h1>
                <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                    Read directly from each server over SSH every five seconds. These readings are
                    not stored; use Fleet history for patterns over time.
                </p>
            </div>
            <button type="button" class="button h-8! px-3! text-[12px]!"
                wire:click="poll" wire:loading.attr="disabled" wire:target="poll">
                <span wire:loading.remove wire:target="poll">Refresh now</span>
                <span wire:loading wire:target="poll">Refreshing…</span>
            </button>
        </header>

        @if ($rows === [])
            <x-empty size="sm" title="No servers to poll"
                description="Add a server and validate its SSH connection. Live readings appear here as soon as it is reachable."
                icon-name="servers">
                <x-slot:contents>
                    <a class="button" href="{{ route('server.index') }}" {{ wireNavigate() }}>View servers</a>
                </x-slot:contents>
            </x-empty>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($rows as $row)
                @php
                    $reachable = (bool) $row['reachable'];
                    $cores = (int) ($row['cores'] ?? 0);
                    $load = (float) ($row['load'] ?? 0);
                    $uptime = trim((string) ($row['uptime'] ?? '')) ?: 'uptime unavailable';
                @endphp
                <div class="application-settings-section">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-[14px] font-semibold">{{ $row['name'] }}</p>
                            <p class="text-[11px] text-neutral-500 dark:text-fg-dim">
                                @if ($reachable)
                                    {{ $cores > 0 ? $cores.' vCPU' : 'vCPU count unavailable' }}
                                    · load {{ number_format($load, 2) }} (1 min)
                                    · {{ $uptime }}
                                @else
                                    No response from this server
                                @endif
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] uppercase {{ $reachable ? 'bg-success/15 text-success' : 'bg-error/15 text-error' }}">
                            {{ $reachable ? 'online' : 'offline' }}
                        </span>
                    </div>

                    @if ($reachable)
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
                            @foreach ([
                                ['CPU', $row['cpu'], '%', 'Average usage of all CPU cores.'],
                                ['RAM', $row['memory'], '%', 'Used memory as a share of total memory.'],
                                ['Disk', $row['disk'], '%', 'Usage of the root filesystem.'],
                            ] as [$label, $value, $unit, $help])
                                @php $percent = min(100, max(0, (float) $value)); @endphp
                                <div>
                                    <p class="text-[10px] uppercase tracking-wide text-neutral-500 dark:text-fg-faint" title="{{ $help }}">{{ $label }}</p>
                                    <p class="text-[18px] font-semibold">{{ number_format($percent, 1) }}{{ $unit }}</p>
                                    <div class="mt-1 h-1 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-white/10"
                                        role="progressbar" aria-label="{{ $label }} usage"
                                        aria-valuenow="{{ round($percent) }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="h-full rounded-full {{ $percent > 85 ? 'bg-error' : ($percent > 65 ? 'bg-warning' : 'bg-success') }}"
                                            style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 rounded-lg border border-warning/30 bg-warning/10 p-3 text-[12px] leading-5 text-neutral-600 dark:text-fg-dim">
                            Dashy could not read metrics from this server. Check that it is online and that
                            SSH access still works, then refresh.
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($rows !== [])
            <p class="text-[11px] leading-5 text-neutral-500 dark:text-fg-dim">
                CPU, memory and disk are sampled from <code>/proc</code> and <code>df</code>. Load is the
                one-minute average, so it can exceed the vCPU count when work is queued.
            </p>
        @endif
    </div>
</div>
