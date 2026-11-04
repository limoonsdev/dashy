<div>
    <x-slot:title>Tools | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Open-source toolbox</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Self-hosted tools that work well next to Dashy. Copy a command, run it on a server, done.
            </p>
        </header>

        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px]">{{ $error }}</div>
        @endif
        @if ($output)
            <pre class="max-h-56 overflow-auto rounded-lg border border-neutral-200 p-3 text-[11px] dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $output }}</pre>
        @endif

        <div class="grid items-end gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Target server</label>
                <select wire:model="serverUuid" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                    @foreach ($servers as $server)
                        <option value="{{ $server->uuid }}">{{ $server->name }}</option>
                    @endforeach
                </select>
            </div>
            <p class="text-[11px] text-neutral-500 dark:text-fg-dim">Starting a container needs a team admin.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($categories as $category)
                <button type="button" wire:click="setCategory('{{ $category }}')"
                    class="button h-8! px-3! text-[12px]! {{ $this->category === $category ? 'button-highlighted' : '' }}">
                    {{ $category }}
                </button>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($tools as $tool)
                <div class="application-settings-section">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-[14px] font-semibold">{{ $tool['name'] }}</p>
                            <p class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-fg-faint">{{ $tool['category'] }}</p>
                        </div>
                        <a href="{{ $tool['url'] }}" target="_blank" rel="noopener noreferrer"
                            class="text-[11px] underline">Source</a>
                    </div>
                    <p class="mt-2 text-[12px] text-neutral-600 dark:text-fg-dim">{{ $tool['description'] }}</p>
                    <pre class="mt-3 overflow-auto rounded-lg border border-neutral-200 p-2 text-[11px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $tool['run'] }}</pre>
                    <div class="mt-2 text-right">
                        <button type="button" class="button h-7! px-2.5! text-[11px]!"
                            wire:click="run('{{ $tool['name'] }}')"
                            wire:confirm="Start {{ $tool['name'] }} on the selected server?">
                            Run on server
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
