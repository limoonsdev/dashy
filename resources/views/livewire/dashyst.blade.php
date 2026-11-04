<div class="fixed right-4 bottom-4 z-[90] flex flex-col items-end gap-3">
    @if ($open)
        <div class="flex h-[min(560px,80vh)] w-[min(400px,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-modal dark:border-white/10 dark:bg-[#141414]">
            <header class="flex items-center justify-between gap-2 border-b border-neutral-200 px-3 py-2 dark:border-white/10">
                <div class="flex items-center gap-2">
                    <span class="flex size-7 items-center justify-center rounded-lg bg-dashy text-white"><x-reicon name="sparkles" class="size-4" /></span>
                    <div>
                        <p class="text-[12px] font-semibold leading-4">Dashyst</p>
                        <p class="text-[10px] text-neutral-500 dark:text-fg-faint">Your operations copilot</p>
                    </div>
                </div>
                <button type="button" wire:click="toggle" aria-label="Close Dashyst"
                    class="rounded-md p-1 text-neutral-500 hover:bg-neutral-100 dark:hover:bg-white/10">
                    <x-reicon name="x" class="size-4" />
                </button>
            </header>

            <div class="flex items-center gap-1 border-b border-neutral-100 px-2 py-1.5 dark:border-white/[0.06]">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-md text-[9px] font-bold text-white"
                    style="background-color: {{ $currentProvider['color'] ?? '#6b16ed' }}"
                    title="{{ $currentProvider['name'] ?? $provider }}">
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($currentProvider['name'] ?? 'AI', 0, 2)) }}
                </span>
                <select wire:model.live="provider" aria-label="Provider"
                    class="min-w-0 flex-1 rounded-md border border-neutral-200 bg-transparent px-1.5 py-1 text-[11px] dark:border-white/10">
                    @foreach ($providers as $entry)
                        <option value="{{ $entry['id'] }}">{{ $entry['name'] }}</option>
                    @endforeach
                </select>
                <input wire:model="model" aria-label="Model" spellcheck="false" list="dashyst-models"
                    class="min-w-0 flex-1 rounded-md border border-neutral-200 bg-transparent px-1.5 py-1 font-mono text-[11px] dark:border-white/10" />
                <datalist id="dashyst-models">
                    @foreach ($providerModels as $modelName)
                        <option value="{{ $modelName }}"></option>
                    @endforeach
                </datalist>
            </div>

            <div class="flex-1 space-y-2 overflow-auto p-3">
                @forelse ($messages as $message)
                    <div @class([
                        'rounded-lg px-2.5 py-2 text-[12px] leading-5 whitespace-pre-wrap',
                        'bg-neutral-100 dark:bg-white/[0.05]' => $message['role'] === 'user',
                        'border border-dashy/25 bg-dashy/[0.05]' => $message['role'] === 'assistant',
                    ])>{{ $message['content'] }}</div>
                @empty
                    <p class="text-[12px] text-neutral-500 dark:text-fg-dim">
                        Ask about a server, a deployment, or what to clean up. I propose one action at a time
                        and wait for your approval.
                    </p>
                @endforelse

                @if ($pendingCommand)
                    <div class="rounded-lg border border-warning/40 bg-warning/10 p-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-warning-700 dark:text-warning">Needs approval</p>
                        <pre class="mt-1 overflow-auto whitespace-pre-wrap font-mono text-[11px]">{{ $pendingCommand }}</pre>
                        <div class="mt-2 flex gap-1.5">
                            <select wire:model="serverUuid" class="min-w-0 flex-1 rounded-md border border-neutral-200 px-1.5 py-1 text-[11px] dark:border-white/10 dark:bg-white/[0.04]">
                                @foreach ($servers as $server)
                                    <option value="{{ $server->uuid }}">{{ $server->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="button h-7! bg-success! px-2! text-[11px]! text-white!" wire:click="approve">Run</button>
                            <button type="button" class="button h-7! px-2! text-[11px]!" wire:click="reject">Cancel</button>
                        </div>
                    </div>
                @endif

                @if ($poll)
                    <div class="rounded-lg border border-neutral-200 p-2.5 dark:border-white/10">
                        <p class="text-[12px] font-semibold">{{ $poll['question'] }}</p>
                        @foreach ($poll['options'] as $index => $option)
                            @php($total = max(1, array_sum($poll['votes'])))
                            <button type="button" wire:click="vote({{ $index }})" class="mt-1.5 block w-full text-left">
                                <span class="flex justify-between text-[11px]"><span>{{ $option }}</span><span>{{ $poll['votes'][$index] }}</span></span>
                                <span class="mt-0.5 block h-1 overflow-hidden rounded-full bg-neutral-200 dark:bg-white/10">
                                    <span class="block h-full rounded-full bg-dashy" style="width: {{ round($poll['votes'][$index] / $total * 100) }}%"></span>
                                </span>
                            </button>
                        @endforeach
                        <button type="button" class="mt-2 text-[10px] underline" wire:click="closePoll">Close poll</button>
                    </div>
                @endif

                @if ($error)
                    <p class="text-[11px] text-error">{{ $error }}</p>
                @endif
            </div>

            <div class="border-t border-neutral-100 p-2 dark:border-white/[0.06]">
                <div class="flex items-end gap-1.5">
                    <textarea wire:model="prompt" rows="2" placeholder="Ask, or write a poll: Question? | A | B"
                        wire:keydown.enter.prevent="send"
                        class="min-w-0 flex-1 resize-none rounded-lg border border-neutral-200 bg-transparent px-2 py-1.5 text-[12px] dark:border-white/10"></textarea>
                    <button type="button" wire:click="send" class="button h-8! px-2.5! text-[11px]!" aria-label="Send">
                        <x-reicon name="sources" class="size-3.5" />
                    </button>
                </div>
                <div class="mt-1 flex items-center justify-between text-[10px] text-neutral-500 dark:text-fg-faint">
                    <button type="button" wire:click="startPoll" class="underline">Start a poll</button>
                    <button type="button" wire:click="clear" class="underline">Clear</button>
                </div>
            </div>
        </div>
    @endif

    <button type="button" wire:click="toggle" aria-label="Open Dashyst"
        class="flex items-center gap-2 rounded-full bg-dashy px-3.5 py-2 text-[12px] font-semibold text-white shadow-lg transition-transform hover:scale-105">
        <x-reicon name="sparkles" class="size-4" />
        Dashyst
    </button>
</div>
