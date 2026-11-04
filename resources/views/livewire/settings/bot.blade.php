<div>
    <x-slot:title>Bot | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Chat bot</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Connect Dashy to the chat platforms your team already uses. Each platform keeps its own token,
                target and embed color, and every command goes through the Dashy API.
            </p>
        </header>

        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Bot platforms">
            @foreach (\App\Models\BotChannel::PLATFORMS as $key => $label)
                <button type="button" wire:click="setPlatform('{{ $key }}')" role="tab"
                    aria-selected="{{ $platform === $key ? 'true' : 'false' }}"
                    class="button h-8! px-3! text-[12px]! {{ $platform === $key ? 'button-highlighted' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <x-application.settings-section title="Connection">
            <div class="grid gap-4 lg:grid-cols-2">
                <x-forms.input wire:model="target" id="target" label="Channel, chat id or group"
                    helper="Where the bot answers and posts notifications." placeholder="123456789012345678" />
                <div>
                    <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Embed color</label>
                    <input type="color" wire:model="embedColor"
                        class="mt-1 h-10 w-full cursor-pointer rounded-lg border border-neutral-200 bg-transparent p-1 dark:border-white/[0.08]" />
                </div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <x-forms.input wire:model="botToken" id="botToken" type="password" label="Bot token" placeholder="••••••••"
                    helper="Encrypted at rest. Leave empty to keep the current token." />
                <x-forms.input wire:model="webhookSecret" id="webhookSecret" type="password" label="Webhook secret"
                    placeholder="••••••••" helper="Used to verify signed webhook deliveries." />
            </div>

            <div class="mt-4 flex items-center gap-3">
                <input id="bot-enabled" type="checkbox" wire:model="enabled"
                    class="rounded border-neutral-300 dark:border-white/20">
                <label for="bot-enabled" class="text-[13px]">Enable the {{ \App\Models\BotChannel::PLATFORMS[$platform] }} bot</label>
            </div>
        </x-application.settings-section>

        <x-application.settings-section title="Allowed features">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (\App\Models\BotChannel::FEATURES as $key => $label)
                    <label class="flex items-start gap-2 text-[12px]">
                        <input type="checkbox" wire:model="features" value="{{ $key }}"
                            class="mt-0.5 rounded border-neutral-300 dark:border-white/20">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </x-application.settings-section>

        <x-application.settings-section title="Bot endpoint">
            <p class="text-[13px] text-neutral-500 dark:text-fg-dim">
                The bot service receives platform webhooks on these paths. Point the platform at them from the
                public URL of this instance:
            </p>
            <pre class="mt-3 overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">{{ url('/bot/discord') }}
{{ url('/bot/telegram') }}
{{ url('/bot/whatsapp') }}
{{ url('/bot/webhook') }}</pre>
            <p class="mt-3 text-[12px] text-neutral-500 dark:text-fg-dim">
                Commands: /help, /status, /servers, /apps, /databases, /deployments, /deploy, /domain, /dns, /whois, /ip.
            </p>
        </x-application.settings-section>

        <div>
            <x-forms.button type="button" wire:click="save" isHighlighted>Save bot settings</x-forms.button>
        </div>
    </div>
</div>
