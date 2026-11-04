<div>
    <x-slot:title>Verify your account | Dashy</x-slot:title>

    <div class="mx-auto flex max-w-md flex-col gap-4 py-10">
        <div>
            <h1 class="text-[22px] font-semibold tracking-tight">Verify your account</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Enter the six-digit code sent to your email. Codes expire after 10 minutes.
            </p>
        </div>

        @if ($message)
            <div class="rounded-lg border border-neutral-200 p-3 text-[12px] dark:border-white/[0.08]">{{ $message }}</div>
        @endif

        <div class="flex flex-col gap-3">
            <x-forms.input wire:model="code" id="code" label="Code" placeholder="123456" />
            @error('code') <p class="text-[12px] text-error">{{ $message }}</p> @enderror
            <x-forms.button type="button" wire:click="verify" isHighlighted>Verify</x-forms.button>
            <button type="button" class="text-[12px] underline" wire:click="send">Send a new code</button>
        </div>
    </div>
</div>
