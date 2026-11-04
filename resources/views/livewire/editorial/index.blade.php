<div class="flex w-full min-w-0 flex-col gap-5">
    <x-slot:title>Editorial | Dashy</x-slot>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Editorial</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Ideas to published posts, with the channels that receive them.
            </p>
        </div>
        <x-forms.button type="button" wire:click="create" class="w-auto">
            <x-reicon name="plus" class="size-3.5" />
            New post
        </x-forms.button>
    </div>

    {{-- Pipeline --}}
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($statuses as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $filter === $key ? 'all' : $key }}')"
                @class([
                    'rounded-xl border p-3 text-left transition-colors',
                    'border-dashy/40 bg-dashy/[0.04]' => $filter === $key,
                    'border-neutral-200 hover:bg-neutral-50 dark:border-white/[0.08] dark:hover:bg-white/[0.03]' => $filter !== $key,
                ])>
                <p class="text-[11px] font-medium text-neutral-500 dark:text-fg-dim">{{ $label }}</p>
                <p class="mt-1 text-[20px] font-semibold leading-6">{{ $counts[$key] ?? 0 }}</p>
            </button>
        @endforeach
    </div>

    @if ($filter !== 'all')
        <div class="flex items-center gap-2 text-[12px]">
            <span class="text-neutral-500 dark:text-fg-dim">Showing {{ $statuses[$filter] ?? $filter }}</span>
            <button type="button" wire:click="$set('filter', 'all')" class="font-medium text-dashy hover:underline">Clear</button>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.3fr_1fr]">
        {{-- List --}}
        <div class="flex flex-col overflow-hidden rounded-xl border border-neutral-200 dark:border-white/[0.08]">
            <div class="hidden grid-cols-[1fr_100px_120px_110px] gap-2 border-b border-neutral-200 px-4 py-2 text-[10px] font-semibold uppercase tracking-wide text-neutral-500 dark:border-white/[0.08] dark:text-fg-faint sm:grid">
                <span>Post</span>
                <span>Status</span>
                <span>Channels</span>
                <span class="text-right">Schedule</span>
            </div>

            @forelse ($posts as $post)
                <div wire:key="post-{{ $post->id }}"
                    @class([
                        'grid cursor-pointer grid-cols-1 gap-2 border-b border-neutral-200 px-4 py-3 text-[12px] last:border-b-0 hover:bg-neutral-50 dark:border-white/[0.06] dark:hover:bg-white/[0.02] sm:grid-cols-[1fr_100px_120px_110px] sm:items-center',
                        'bg-dashy/[0.03]' => $editing === $post->id,
                    ])
                    wire:click="edit({{ $post->id }})">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $post->title }}</p>
                        <p class="truncate text-[11px] text-neutral-500 dark:text-fg-dim">
                            {{ $post->author?->name ?? 'Unknown author' }} · {{ $post->readingMinutes() }} min read
                        </p>
                    </div>
                    <div>
                        @php
                            $tone = match ($post->status) {
                                'published' => 'text-emerald-600 dark:text-emerald-400',
                                'scheduled' => 'text-amber-600 dark:text-amber-400',
                                'review' => 'text-violet-600 dark:text-violet-400',
                                'archived' => 'text-neutral-400',
                                default => 'text-neutral-600 dark:text-fg-dim',
                            };
                        @endphp
                        <span class="inline-flex rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold {{ $tone }} dark:bg-white/[0.05]">
                            {{ $statuses[$post->status] ?? $post->status }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1">
                        @forelse ((array) $post->channels as $channel)
                            <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] dark:bg-white/[0.06]">{{ $channel }}</span>
                        @empty
                            <span class="text-[11px] text-neutral-400">—</span>
                        @endforelse
                    </div>
                    <div class="text-[11px] text-neutral-500 dark:text-fg-dim sm:text-right">
                        {{ $post->published_at?->format('M j, H:i') ?? $post->scheduled_at?->format('M j, H:i') ?? '—' }}
                    </div>
                </div>
            @empty
                <p class="px-4 py-10 text-center text-[12px] text-neutral-500 dark:text-fg-dim">
                    No posts in this state yet. Start with an idea.
                </p>
            @endforelse
        </div>

        {{-- Editor --}}
        <div class="flex flex-col rounded-xl border border-neutral-200 dark:border-white/[0.08]">
            @if ($editing === null)
                <div class="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center">
                    <x-reicon name="sources" class="size-6 text-neutral-400" />
                    <p class="text-[13px] font-medium">Select a post</p>
                    <p class="text-[11px] text-neutral-500 dark:text-fg-dim">Or create a new one to open the editor.</p>
                </div>
            @else
                <form wire:submit="save" class="flex flex-col gap-3 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[13px] font-semibold">{{ $editing ? 'Edit post' : 'New post' }}</p>
                        <button type="button" wire:click="close" class="text-[11px] text-neutral-500 hover:underline dark:text-fg-dim">Close</button>
                    </div>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-medium">Title</span>
                        <input type="text" wire:model="title"
                            class="w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                        @error('title')<span class="text-[11px] text-red-500">{{ $message }}</span>@enderror
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-medium">Excerpt</span>
                        <textarea wire:model="excerpt" rows="2"
                            class="w-full rounded-lg border border-neutral-200 p-2 text-[12px] dark:border-white/[0.08] dark:bg-white/[0.02]"></textarea>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-medium">Body (HTML or Markdown)</span>
                        <textarea wire:model="body" rows="8"
                            class="w-full rounded-lg border border-neutral-200 p-2 font-mono text-[12px] dark:border-white/[0.08] dark:bg-white/[0.02]"></textarea>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] font-medium">Status</span>
                            <select wire:model="status"
                                class="w-full rounded-lg border border-neutral-200 p-2 text-[12px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                                @foreach ($statuses as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] font-medium">Schedule</span>
                            <input type="datetime-local" wire:model="scheduledAt"
                                class="w-full rounded-lg border border-neutral-200 p-2 text-[12px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                        </label>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <span class="text-[11px] font-medium">Channels</span>
                        <div class="grid gap-1.5 sm:grid-cols-2">
                            @foreach ($channelOptions as $key => $channel)
                                <label @class([
                                    'flex items-start gap-2 rounded-lg border p-2 text-[11px]',
                                    'border-neutral-200 dark:border-white/[0.08]' => $channel['configured'],
                                    'border-dashed border-neutral-200 opacity-60 dark:border-white/[0.08]' => ! $channel['configured'],
                                ])>
                                    <input type="checkbox" value="{{ $key }}" wire:model="channels" class="mt-0.5">
                                    <span class="min-w-0">
                                        <span class="font-medium">{{ $channel['label'] }}</span>
                                        @unless ($channel['configured'])
                                            <span class="block text-[10px] text-amber-600 dark:text-amber-400">not configured</span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 border-t border-neutral-200 pt-3 dark:border-white/[0.08]">
                        <x-forms.button type="submit" class="w-auto">Save</x-forms.button>
                        @if ($editing)
                            <button type="button" wire:click="publish({{ $editing }})" wire:loading.attr="disabled"
                                class="rounded-lg border border-neutral-200 px-2.5 py-1.5 text-[12px] font-medium hover:bg-neutral-100 dark:border-white/[0.08] dark:hover:bg-white/[0.04]">
                                Publish now
                            </button>
                            <button type="button" wire:click="transition({{ $editing }}, 'review')"
                                class="rounded-lg px-2 py-1.5 text-[11px] font-medium text-neutral-500 hover:bg-neutral-100 dark:text-fg-dim dark:hover:bg-white/[0.04]">
                                Send to review
                            </button>
                            <button type="button" wire:click="remove({{ $editing }})" wire:confirm="Delete this post?"
                                class="ml-auto rounded-lg px-2 py-1.5 text-[11px] font-medium text-red-600 hover:bg-red-500/10 dark:text-red-400">
                                Delete
                            </button>
                        @endif
                    </div>
                </form>
            @endif

            @if ($results !== [])
                <div class="border-t border-neutral-200 p-4 dark:border-white/[0.08]">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-fg-faint">
                        Last publish · {{ $resultsFor }}
                    </p>
                    <div class="mt-2 flex flex-col gap-1.5">
                        @foreach ($results as $result)
                            <div class="flex items-start gap-2 text-[11px]">
                                <span class="{{ $result['ok'] ? 'text-emerald-500' : 'text-red-500' }}">{{ $result['ok'] ? '●' : '○' }}</span>
                                <span class="min-w-0">
                                    <span class="font-medium">{{ $result['channel'] }}</span>
                                    <span class="text-neutral-500 dark:text-fg-dim"> — {{ $result['message'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
