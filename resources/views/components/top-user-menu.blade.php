@props([
    'sidebar' => false,
])

@php
    $user = auth()->user();
    $userName = $user?->name ?? 'Account';
    $userEmail = $user?->email ?? '';
    $userInitial = strtoupper(mb_substr($user?->name ?: ($user?->email ?: 'A'), 0, 1));
@endphp
<div @class(['relative', 'min-w-0 flex-1' => $sidebar]) x-data="{
    open: false,
    panelMaxHeight: null,
    avatarUrl: @js($user?->avatar_path ? profile_avatar_url($user) : null),
    openPanel() {
        this.fitPanel();
        this.open = true;
    },
    fitPanel() {
        const rect = this.$refs.userMenuButton.getBoundingClientRect();
        const space = @js($sidebar) ? rect.top : window.innerHeight - rect.bottom;
        this.panelMaxHeight = Math.max(space - 12, 160) + 'px';
    },
    closePanel() {
        this.open = false;
    },
}" @avatar-updated.window="avatarUrl = $event.detail.url" @keydown.escape.window="closePanel()" @resize.window="open && fitPanel()"
    @click.outside="closePanel()">
    <button type="button" x-ref="userMenuButton" @click="open ? closePanel() : openPanel()"
        title="{{ $userName }}" aria-label="Account menu for {{ $userName }}"
        @if ($sidebar) :class="collapsed ? 'w-8 px-0' : 'w-full'" @endif
        @class([
            'button rounded-lg px-2 normal-case',
            'min-w-0' => $sidebar,
        ])>
        <img x-cloak x-show="avatarUrl" :src="avatarUrl" alt="{{ $userName }}"
            class="size-5 shrink-0 rounded-full object-cover">
        <span x-show="!avatarUrl"
            class="flex size-5 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-[11px] font-semibold text-neutral-700 dark:bg-white/[0.1] dark:text-fg">
            {{ $userInitial }}
        </span>
        @if ($sidebar)
            <span class="min-w-0 flex-1 truncate text-left text-xs font-medium" :class="collapsed && 'hidden'">{{ $userName }}</span>
        @endif
        <svg class="size-3.5 shrink-0 text-neutral-400 dark:text-fg-faint transition-transform"
            :class="[open && 'rotate-180', {{ $sidebar ? "collapsed && 'hidden'" : 'false' }}]"
            viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>

    {{-- In the sidebar the panel is fixed to the button, so the scrolling sidebar does not clip it.
         The panel scrolls when it is taller than the space next to the button. --}}
    <div x-show="open" x-cloak :style="{ maxHeight: panelMaxHeight }"
        @if ($sidebar) x-anchor.top-start.fixed.offset.4="$refs.userMenuButton" @endif
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        @class([
            'top-user-menu-panel listbox-panel z-[90]! w-52! min-w-0! overflow-y-auto!',
            'right-0! left-auto!' => ! $sidebar,
            'top-user-menu-panel-sidebar origin-bottom-left' => $sidebar,
            'origin-top-right' => ! $sidebar,
        ])>
        <div class="min-w-0 px-2 py-1.5">
            <div class="truncate text-[13px] font-semibold text-black dark:text-fg">{{ $userName }}</div>
            <div class="truncate text-[11px] text-neutral-500 dark:text-fg-faint">{{ $userEmail }}</div>
        </div>
        <div class="my-1 h-px bg-neutral-200 dark:bg-white/[0.07]"></div>

        <a href="{{ route('profile') }}" {{ wireNavigate() }} class="listbox-option">
            <span class="flex items-center gap-2">
                <x-reicon name="profile" class="size-4 opacity-80" />
                Profile
            </span>
        </a>
        <a href="{{ route('profile.appearance') }}" {{ wireNavigate() }} class="listbox-option">
            <span class="flex items-center gap-2">
                <x-reicon name="settings" class="size-4 opacity-80" />
                Appearance
            </span>
        </a>

        <div class="px-2 pb-2" x-data="themeControls()">
            <div class="mb-1.5 flex items-center justify-between">
                <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-neutral-400 dark:text-fg-faint">Theme</span>
                <a href="{{ route('profile.appearance') }}" {{ wireNavigate() }}
                    class="text-[10px] font-medium text-neutral-400 hover:text-black dark:text-fg-faint dark:hover:text-fg">Edit</a>
            </div>
            <div class="grid grid-cols-6 gap-1.5">
                <template x-for="preset in presets" :key="preset.name">
                    <button type="button" @click="applyPreset(preset)" :title="preset.name"
                        :aria-label="`Use ${preset.name} theme`"
                        class="group relative aspect-square overflow-hidden rounded-md border border-black/10 transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-dashy dark:border-white/10"
                        :style="`background: ${preset.theme ? preset.theme.background : '#0a0a0a'}`">
                        <span class="absolute inset-y-0 left-0 w-[36%]"
                            :style="`background: ${preset.theme ? preset.theme.sidebar : '#1a0f2d'}`"></span>
                        <span class="absolute right-1 top-1 size-1.5 rounded-full"
                            :style="`background: ${preset.theme ? preset.theme.accent : '#6b16ed'}`"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="my-1 h-px bg-neutral-200 dark:bg-white/[0.07]"></div>

        <livewire:settings-dropdown trigger="account-menu" />
        <a href="https://dashy.io/docs" target="_blank" rel="noopener noreferrer" class="listbox-option">
            <span class="flex items-center gap-2">
                <x-reicon name="documentation" class="size-4 opacity-80" />
                Documentation
            </span>
        </a>
        <x-modal-input title="How can we help?">
            <x-slot:content>
                <div class="listbox-option cursor-pointer" @click="closePanel()">
                    <span class="flex items-center gap-2">
                        <x-reicon name="feedback" class="size-4 opacity-80" />
                        Feedback
                    </span>
                </div>
            </x-slot:content>
            <livewire:help />
        </x-modal-input>
        @if (isSubscribed() || !isCloud())
            <a href="https://dashy.io/sponsorships" target="_blank" rel="noopener noreferrer"
                class="listbox-option">
                <span class="flex items-center gap-2">
                    <x-reicon name="sponsor" class="size-4 text-pink-500" />
                    Sponsor us
                </span>
            </a>
        @endif

        <div class="my-1 h-px bg-neutral-200 dark:bg-white/[0.07]"></div>

        <form action="/logout" method="POST">
            @csrf
            <button type="submit" class="listbox-option w-full text-left text-error dark:text-error">
                <span class="flex items-center gap-2">
                    <x-reicon name="logout" class="size-4 opacity-90" />
                    Log out
                </span>
            </button>
        </form>
    </div>
</div>
