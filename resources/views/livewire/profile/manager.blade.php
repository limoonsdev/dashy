<div class="flex w-full min-w-0 flex-col gap-5">
    <x-slot:title>Profile manager | Dashy</x-slot>

    <div>
        <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Profile manager</h1>
        <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
            Your identity, every place you are signed in, and the tokens that can act as you.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_1.4fr]">
        <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-white/[0.08]">
            <div class="flex items-center gap-3">
                <img src="{{ profile_avatar_url(auth()->user()) }}" alt=""
                    class="size-12 rounded-full object-cover ring-1 ring-black/5 dark:ring-white/10">
                <div class="min-w-0">
                    <p class="truncate text-[14px] font-semibold">{{ auth()->user()->name }}</p>
                    <p class="truncate text-[12px] text-neutral-500 dark:text-fg-dim">{{ auth()->user()->email }}</p>
                </div>
            </div>

            <div class="flex flex-col gap-2 border-t border-neutral-200 pt-3 text-[12px] dark:border-white/[0.08]">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-neutral-500 dark:text-fg-dim">Team</span>
                    <span class="font-medium">{{ currentTeam()?->name }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-neutral-500 dark:text-fg-dim">Role</span>
                    <span class="font-medium">{{ auth()->user()->isAdminOfTeam(currentTeam()->id) ? 'Administrator' : 'Member' }}</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-neutral-500 dark:text-fg-dim">Two-factor</span>
                    <span class="font-medium">{{ auth()->user()->two_factor_confirmed_at ? 'Enabled' : 'Not enabled' }}</span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-neutral-200 pt-3 dark:border-white/[0.08]">
                <a href="{{ route('profile') }}" class="rounded-lg border border-neutral-200 px-2.5 py-1.5 text-[12px] font-medium hover:bg-neutral-100 dark:border-white/[0.08] dark:hover:bg-white/[0.04]">Edit profile</a>
                <a href="{{ route('profile.security') }}" class="rounded-lg border border-neutral-200 px-2.5 py-1.5 text-[12px] font-medium hover:bg-neutral-100 dark:border-white/[0.08] dark:hover:bg-white/[0.04]">Security</a>
                <a href="{{ route('profile.appearance') }}" class="rounded-lg border border-neutral-200 px-2.5 py-1.5 text-[12px] font-medium hover:bg-neutral-100 dark:border-white/[0.08] dark:hover:bg-white/[0.04]">Appearance</a>
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 dark:border-white/[0.08]">
            <div class="flex items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3 dark:border-white/[0.08]">
                <div>
                    <p class="text-[13px] font-semibold">Active sessions</p>
                    <p class="text-[11px] text-neutral-500 dark:text-fg-dim">Browsers currently signed in to your account.</p>
                </div>
                <button type="button" wire:click="revokeOtherSessions" wire:confirm="Sign out every other session?"
                    class="shrink-0 rounded-lg border border-neutral-200 px-2.5 py-1.5 text-[12px] font-medium hover:bg-neutral-100 dark:border-white/[0.08] dark:hover:bg-white/[0.04]">
                    Sign out others
                </button>
            </div>

            <div class="flex flex-col divide-y divide-neutral-200 dark:divide-white/[0.06]">
                @forelse ($sessions as $session)
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-[12px]" wire:key="session-{{ $session['id'] }}">
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ $session['agent'] }}
                                @if ($session['current'])
                                    <span class="ml-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">this device</span>
                                @endif
                            </p>
                            <p class="truncate text-[11px] text-neutral-500 dark:text-fg-dim">{{ $session['ip'] ?: 'unknown address' }}</p>
                        </div>
                        <span class="shrink-0 text-[11px] text-neutral-500 dark:text-fg-dim">{{ $session['last_activity'] }}</span>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-[12px] text-neutral-500 dark:text-fg-dim">No stored sessions.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="flex flex-col rounded-xl border border-neutral-200 dark:border-white/[0.08]">
        <div class="flex items-center justify-between gap-3 border-b border-neutral-200 px-4 py-3 dark:border-white/[0.08]">
            <div>
                <p class="text-[13px] font-semibold">Access tokens</p>
                <p class="text-[11px] text-neutral-500 dark:text-fg-dim">API and CLI tokens that can act without your password.</p>
            </div>
            <a href="{{ route('security.api-tokens') }}" class="shrink-0 rounded-lg border border-neutral-200 px-2.5 py-1.5 text-[12px] font-medium hover:bg-neutral-100 dark:border-white/[0.08] dark:hover:bg-white/[0.04]">
                Manage API tokens
            </a>
        </div>

        <div class="flex flex-col divide-y divide-neutral-200 dark:divide-white/[0.06]">
            @forelse ($tokens as $token)
                <div class="flex items-center justify-between gap-3 px-4 py-2.5" wire:key="token-{{ $token['id'] }}">
                    <div class="min-w-0">
                        <p class="truncate text-[12px] font-medium">
                            {{ $token['name'] }}
                            @if ($token['is_cli'])
                                <span class="ml-1 rounded-full bg-dashy/10 px-2 py-0.5 text-[10px] font-semibold text-dashy">CLI</span>
                            @endif
                        </p>
                        <p class="truncate text-[11px] text-neutral-500 dark:text-fg-dim">
                            {{ $token['abilities'] }} · used {{ $token['last_used_at'] }} · expires {{ $token['expires_at'] }}
                        </p>
                    </div>
                    <button type="button" wire:click="revokeToken({{ $token['id'] }})" wire:confirm="Revoke this token?"
                        class="shrink-0 rounded-lg px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-500/10 dark:text-red-400">
                        Revoke
                    </button>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-[12px] text-neutral-500 dark:text-fg-dim">No tokens. Generate one in Settings → CLI.</p>
            @endforelse
        </div>
    </div>
</div>
