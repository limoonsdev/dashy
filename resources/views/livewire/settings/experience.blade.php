<div>
    <x-slot:title>Experience | Dashy</x-slot:title>

    <x-settings.layout>
        <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
            <x-application.settings-section title="Experience"
                helper="How this instance feels in the browser and in the terminal.">
                <form wire:submit="save" class="flex flex-col gap-5">
                    <div class="flex items-start justify-between gap-4 rounded-lg border border-neutral-200 p-4 dark:border-white/[0.08]">
                        <div class="min-w-0">
                            <p class="text-[13px] font-medium">Boot screen</p>
                            <p class="mt-1 text-[11px] leading-5 text-neutral-500 dark:text-fg-dim">
                                Show the animated DASHY splash while the first page loads. The screen fades
                                out as soon as the workspace is ready.
                            </p>
                        </div>
                        <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                            <input type="checkbox" wire:model="bootScreen" class="peer sr-only">
                            <span class="h-5 w-9 rounded-full bg-neutral-300 transition-colors peer-checked:bg-dashy dark:bg-white/10"></span>
                            <span class="absolute left-0.5 size-4 rounded-full bg-white transition-transform peer-checked:translate-x-4"></span>
                        </label>
                    </div>

                    <div class="flex items-start justify-between gap-4 rounded-lg border border-neutral-200 p-4 dark:border-white/[0.08]">
                        <div class="min-w-0">
                            <p class="text-[13px] font-medium">Dashy CLI pairing</p>
                            <p class="mt-1 text-[11px] leading-5 text-neutral-500 dark:text-fg-dim">
                                Allow terminals to exchange a sign-in code for a scoped token. Turning this
                                off blocks new pairings; tokens that already exist keep working until revoked.
                            </p>
                        </div>
                        <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                            <input type="checkbox" wire:model="cliPairing" class="peer sr-only">
                            <span class="h-5 w-9 rounded-full bg-neutral-300 transition-colors peer-checked:bg-dashy dark:bg-white/10"></span>
                            <span class="absolute left-0.5 size-4 rounded-full bg-white transition-transform peer-checked:translate-x-4"></span>
                        </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="flex flex-col gap-1.5">
                            <span class="text-[12px] font-medium">Live metrics refresh (seconds)</span>
                            <input type="number" min="5" max="120" wire:model="metricsRefresh"
                                class="w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                            <span class="text-[11px] text-neutral-500 dark:text-fg-dim">Between 5 and 120 seconds.</span>
                            @error('metricsRefresh')<span class="text-[11px] text-red-500">{{ $message }}</span>@enderror
                        </label>

                        <label class="flex flex-col gap-1.5">
                            <span class="text-[12px] font-medium">Metric retention (days)</span>
                            <input type="number" min="1" max="365" wire:model="metricsRetention"
                                class="w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                            <span class="text-[11px] text-neutral-500 dark:text-fg-dim">Sentinel history older than this is pruned.</span>
                            @error('metricsRetention')<span class="text-[11px] text-red-500">{{ $message }}</span>@enderror
                        </label>
                    </div>

                    <div>
                        <x-forms.button type="submit" class="w-auto">
                            Save experience settings
                        </x-forms.button>
                    </div>
                </form>
            </x-application.settings-section>
        </div>
    </x-settings.layout>
</div>
