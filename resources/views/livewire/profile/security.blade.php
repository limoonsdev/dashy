<div>
    <x-slot:title>Account security | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 max-w-2xl flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Account security</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Ask for a one-time code by email every time you sign in.
            </p>
        </header>

        @if ($notice)
            <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-[12px]">{{ $notice }}</div>
        @endif
        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px]">{{ $error }}</div>
        @endif

        <x-application.settings-section title="Two-factor by email">
            <label class="flex items-center gap-2 text-[13px]">
                <input type="checkbox" wire:model="twoFactorEnabled" class="rounded border-neutral-300 dark:border-white/20">
                Require a code at sign-in
            </label>
            <div class="mt-4">
                <x-forms.input wire:model="twoFactorEmail" id="twoFactorEmail" label="Send codes to" />
            </div>
            <div class="mt-4">
                <x-forms.button type="button" wire:click="save" isHighlighted>Save</x-forms.button>
            </div>
        </x-application.settings-section>
    </div>
</div>
