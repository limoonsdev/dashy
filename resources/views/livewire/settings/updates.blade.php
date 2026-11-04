<div>
    <x-slot:title>
        Update Settings | Dashy
    </x-slot>

    <x-settings.layout>
        <form wire:submit="submit" class="application-settings-form flex min-w-0 flex-col gap-6">
            {{-- Exclude is_auto_update_enabled (instantSave) so the bar does not flash. --}}
            <x-unsaved-bar action="submit" targets="update_check_frequency,auto_update_frequency,update_channel,update_custom_tag" />

            <x-application.settings-section title="Updates"
                helper="Install the latest Dashy version manually when an update is available.">
                <x-slot:actions>
                    <livewire:upgrade :full-button="true" key="settings-upgrade" />
                    <x-forms.button type="button" wire:click="checkManually">
                        <x-reicon name="refresh" class="size-3.5" />
                        Check now
                    </x-forms.button>
                </x-slot:actions>

                @if ($check !== [])
                    <div class="mb-4 rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[12px] font-semibold">Latest check</p>
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium {{ $check['available'] ? 'text-warning' : 'text-success' }}">
                                <span class="size-1.5 rounded-full {{ $check['available'] ? 'bg-warning' : 'bg-success' }}"></span>
                                {{ $check['available'] ? 'Update available' : 'Up to date' }}
                            </span>
                        </div>
                        <dl class="mt-2 grid gap-2 text-[12px] sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Running</dt>
                                <dd class="font-mono">{{ $check['current'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Latest</dt>
                                <dd class="font-mono">{{ $check['latest'] !== '' ? $check['latest'] : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Channel</dt>
                                <dd>{{ $check['channel'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Checked</dt>
                                <dd>{{ \Illuminate\Support\Carbon::parse($check['checked_at'])->diffForHumans() }}</dd>
                            </div>
                        </dl>
                        @if ($check['latest_commit'])
                            <p class="mt-2 text-[11px] text-neutral-500 dark:text-fg-dim">
                                {{ $check['branch'] }} head:
                                <a href="{{ $check['latest_commit']['url'] }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $check['latest_commit']['short'] }}</a>
                                — {{ $check['latest_commit']['message'] }}
                            </p>
                        @endif
                        @if ($check['release'])
                            <p class="mt-1 text-[11px]">
                                <a href="{{ $check['release']['url'] }}" target="_blank" rel="noopener noreferrer" class="underline">
                                    Release {{ $check['release']['tag'] }}{{ $check['release']['name'] !== $check['release']['tag'] ? ' — '.$check['release']['name'] : '' }}
                                </a>
                            </p>
                        @endif
                        @if ($check['error'])
                            <p class="mt-2 text-[11px] text-warning">Last error: {{ $check['error'] }}</p>
                        @endif
                    </div>
                @endif

                <div class="grid gap-4 lg:grid-cols-2">
                    {{-- Not live: a live update clears the dirty state, so the unsaved bar never opened for a channel change. --}}
                    <x-forms.listbox id="update_channel" label="Channel" :options="[
                        ['value' => 'stable', 'label' => 'Stable', 'description' => 'Stable releases'],
                        ['value' => 'preview', 'label' => 'Preview', 'description' => 'Betas and release candidates, then the stable release'],
                        ['value' => 'nightly', 'label' => 'Nightly', 'description' => 'A build of the next version twice a day, then every release'],
                        ['value' => 'custom', 'label' => 'Custom image', 'description' => 'A pinned image, such as the build of a pull request'],
                    ]"
                        helper="The versions this instance upgrades to." />

                    <div x-show="$wire.update_channel === 'custom'" @if ($update_channel !== 'custom') x-cloak @endif>
                        <x-forms.input required id="update_custom_tag" label="Image tag" placeholder="pr-1234"
                            x-bind:required="$wire.update_channel === 'custom'"
                            helper="A tag of ghcr.io/dashyapp/dashy, such as pr-1234 for the build of pull request 1234." />
                    </div>
                    <div x-show="$wire.update_channel !== 'custom'" @if ($update_channel === 'custom') x-cloak @endif
                        class="hidden lg:block"></div>

                    <div x-show="$wire.update_channel === 'nightly'" @if ($update_channel !== 'nightly') x-cloak @endif
                        class="lg:col-span-2">
                        <x-callout type="danger" title="Nightly builds can break this instance">
                            Nightly builds are untested builds of the next version. Things can break at any time and
                            breaking changes are possible, including database changes a stable release does not expect.
                            Back up first and use nightly only on an instance you can rebuild. After switching back to stable,
                            the instance keeps getting the nightlies of its version until that version is released as stable.
                        </x-callout>
                    </div>
                    <div x-show="$wire.update_channel === 'custom'" @if ($update_channel !== 'custom') x-cloak @endif
                        class="lg:col-span-2">
                        <x-callout type="danger" title="A custom image can break this instance">
                            The image installs only when you upgrade to it here, and automatic updates pause until you
                            switch back to a channel. Pull request builds are untested and can include database changes
                            a later release does not expect.
                        </x-callout>
                    </div>

                    <x-forms.input required id="update_check_frequency" label="Check frequency"
                        placeholder="0 * * * *"
                        helper="A cron expression or preset such as hourly, daily, weekly, monthly, or yearly." />

                    <x-forms.listbox id="docker_registry_url" label="Docker registry" :options="[
                        ['value' => 'docker.io', 'label' => 'Docker Hub'],
                        ['value' => 'ghcr.io', 'label' => 'GitHub Container Registry'],
                    ]"
                        helper="Switch registries if the current source is rate limited." />

                    @if (!is_null(config('constants.dashy.autoupdate', null)))
                        <x-forms.listbox disabled id="is_auto_update_enabled" label="Automatic updates"
                            helper="Controlled by the AUTOUPDATE environment variable." :options="[
                                ['value' => true, 'label' => 'Enabled'],
                                ['value' => false, 'label' => 'Disabled'],
                            ]" />
                    @else
                        <x-forms.listbox id="is_auto_update_enabled" label="Automatic updates"
                            onChange="instantSave" :options="[
                                ['value' => true, 'label' => 'Enabled'],
                                ['value' => false, 'label' => 'Disabled'],
                            ]" />
                    @endif

                    @if (is_null(config('constants.dashy.autoupdate', null)) && $is_auto_update_enabled)
                        <x-forms.input required id="auto_update_frequency" label="Update frequency"
                            placeholder="0 0 * * *"
                            helper="Cron expression or preset for installing updates." />
                    @else
                        <x-forms.input label="Update frequency" disabled placeholder="Disabled" />
                    @endif
                </div>
            </x-application.settings-section>
        </form>
    </x-settings.layout>
</div>
