<div>
    <x-slot:title>Tools | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Toolbox</h1>
                <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                    {{ count($tools) }} open-source projects that run well next to Dashy. Read the docs, open the
                    website, or install one on a server in a click.
                </p>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="text-[11px] text-neutral-500 dark:text-fg-faint" for="tool-server">Install target</label>
                    <select id="tool-server" wire:model="serverUuid"
                        class="mt-1 rounded-lg border border-neutral-200 p-2 text-[12px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                        @foreach ($servers as $server)
                            <option value="{{ $server->uuid }}">{{ $server->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </header>

        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px]">{{ $error }}</div>
        @endif
        @if ($output)
            <pre class="max-h-56 overflow-auto rounded-lg border border-neutral-200 p-3 text-[11px] dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $output }}</pre>
        @endif

        <div class="flex flex-wrap gap-2">
            @foreach ($categories as $category)
                <button type="button" wire:click="setCategory('{{ $category }}')"
                    class="button h-8! px-3! text-[12px]! {{ $this->category === $category ? 'button-highlighted' : '' }}">
                    {{ $category }}
                </button>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @foreach ($tools as $tool)
                <div class="flex flex-col rounded-xl border border-neutral-200 p-4 transition-colors hover:border-neutral-300 dark:border-white/[0.08] dark:hover:border-white/15">
                    <div class="flex items-start gap-3">
                        <span class="relative flex size-10 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-[13px] font-bold text-neutral-500 dark:bg-white/[0.06] dark:text-fg-dim"
                            style="background-color: #{{ $tool['color'] }}1a">
                            <img src="https://cdn.simpleicons.org/{{ $tool['logo'] }}/{{ $tool['color'] }}" alt=""
                                width="24" height="24" loading="lazy"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                            <span class="absolute inset-0 hidden items-center justify-center">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($tool['name'], 0, 2)) }}
                            </span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[14px] font-semibold">{{ $tool['name'] }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-neutral-500 dark:text-fg-faint">{{ $tool['category'] }}</p>
                        </div>
                    </div>

                    <p class="mt-2 flex-1 text-[12px] leading-5 text-neutral-600 dark:text-fg-dim">{{ $tool['description'] }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        <button type="button" class="button h-7! bg-dashy! px-2.5! text-[11px]! text-white!"
                            wire:click="run('{{ $tool['name'] }}')"
                            wire:confirm="Install {{ $tool['name'] }} on the selected server?">
                            <x-reicon name="deploy-canvas" class="size-3" />
                            Install
                        </button>
                        <a href="{{ $tool['docs'] }}" target="_blank" rel="noopener noreferrer"
                            class="button h-7! px-2.5! text-[11px]!">
                            <x-reicon name="documentation" class="size-3" />
                            Docs
                        </a>
                        <a href="{{ $tool['website'] }}" target="_blank" rel="noopener noreferrer"
                            class="button h-7! px-2.5! text-[11px]!">
                            <x-reicon name="globe" class="size-3" />
                            Website
                        </a>
                        <a href="{{ $tool['url'] }}" target="_blank" rel="noopener noreferrer"
                            class="ml-auto text-[11px] text-neutral-500 underline dark:text-fg-faint">
                            Source
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
