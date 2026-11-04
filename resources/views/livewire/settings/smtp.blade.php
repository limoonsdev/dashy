<div>
    <x-slot:title>Mail | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Outgoing mail</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Used for verification codes and account security. Point it at Mailpit, Postal, a local
                Postfix, or any SMTP relay. Credentials are encrypted.
            </p>
        </header>

        @if ($notice)
            <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-[12px]">{{ $notice }}</div>
        @endif
        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px]">{{ $error }}</div>
        @endif

        <x-application.settings-section title="SMTP relay">
            <label class="flex items-center gap-2 text-[13px]">
                <input type="checkbox" wire:model="enabled" class="rounded border-neutral-300 dark:border-white/20">
                Enable outgoing mail
            </label>

            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <x-forms.input wire:model="host" id="host" label="Host" placeholder="127.0.0.1" />
                <x-forms.input wire:model="port" id="port" type="number" label="Port" />
                <div>
                    <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Encryption</label>
                    <select wire:model="encryption" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                        <option value="none">None (local relay)</option>
                        <option value="tls">STARTTLS</option>
                        <option value="ssl">SSL</option>
                    </select>
                </div>
                <x-forms.input wire:model="username" id="username" label="Username" />
                <x-forms.input wire:model="password" id="password" type="password" label="Password" />
                <x-forms.input wire:model="fromAddress" id="fromAddress" label="From address" />
                <x-forms.input wire:model="fromName" id="fromName" label="From name" />
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-forms.button type="button" wire:click="save" isHighlighted>Save</x-forms.button>
                <x-forms.button type="button" wire:click="sendTest">Send test email</x-forms.button>
                <x-forms.button type="button" wire:click="sendOtpTest">Send me a code</x-forms.button>
            </div>
        </x-application.settings-section>

        <x-application.settings-section title="Local relay">
            <p class="text-[12px] text-neutral-500 dark:text-fg-dim">
                No relay yet? Run Mailpit next to Dashy; it accepts mail on port 1025 and shows every message
                in a web inbox on port 8025.
            </p>
            <pre class="mt-3 overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] dark:border-white/[0.08] dark:bg-white/[0.02]">docker run -d --name mailpit --restart unless-stopped -p 1025:1025 -p 8025:8025 axllent/mailpit</pre>
        </x-application.settings-section>
    </div>
</div>
