<div>
    <x-slot:title>Assistant | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">AI assistant</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                An operations assistant wired to your own endpoint. It answers with a short explanation and,
                when useful, one shell command to run on a server you choose.
            </p>
        </header>

        @unless ($settings->configured())
            <div class="rounded-lg border border-warning/40 bg-warning/10 p-3 text-[12px]">
                Add an endpoint and a model in
                <a href="{{ route('settings.ai') }}" class="font-semibold underline">Settings → AI assistant</a>
                before using the assistant.
            </div>
        @endunless

        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px]">{{ $error }}</div>
        @endif

        <x-application.settings-section title="Conversation">
            <div class="flex max-h-[28rem] flex-col gap-3 overflow-auto">
                @forelse ($messages as $message)
                    <div @class([
                        'rounded-lg border p-3 text-[13px] whitespace-pre-wrap',
                        'border-neutral-200 dark:border-white/[0.08]' => $message['role'] === 'user',
                        'border-dashy/30 bg-dashy/[0.04] dark:border-brand-light/25' => $message['role'] === 'assistant',
                    ])>
                        <span class="mb-1 block text-[10px] uppercase tracking-wide text-neutral-500 dark:text-fg-faint">
                            {{ $message['role'] }}
                        </span>
                        {{ $message['content'] }}
                    </div>
                @empty
                    <p class="text-[13px] text-neutral-500 dark:text-fg-dim">
                        Ask something like “why did my last deployment fail?” or “which server is low on disk?”.
                    </p>
                @endforelse
            </div>

            <div class="mt-4 flex items-end gap-2">
                <div class="flex-1">
                    <x-forms.input wire:model="prompt" id="prompt" label="Message" placeholder="Check disk usage on the production server" />
                </div>
                <x-forms.button type="button" wire:click="send" isHighlighted>Send</x-forms.button>
                <button type="button" class="button h-8! px-3! text-[12px]!" wire:click="clear">Clear</button>
            </div>
        </x-application.settings-section>

        @if ($proposedCommand)
            <x-application.settings-section title="Proposed command">
                <pre class="overflow-auto rounded-lg border border-neutral-200 p-3 font-mono text-[12px] dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $proposedCommand }}</pre>
                <div class="mt-3 grid items-end gap-4 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Run on</label>
                        <select wire:model="serverUuid" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                            @foreach ($servers as $server)
                                <option value="{{ $server->uuid }}">{{ $server->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-forms.button type="button" wire:click="runCommand">Run command</x-forms.button>
                </div>
                @if ($commandOutput)
                    <pre class="mt-3 max-h-64 overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $commandOutput }}</pre>
                @endif
            </x-application.settings-section>
        @endif
    </div>
</div>
