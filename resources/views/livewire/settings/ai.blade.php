<div>
    <x-slot:title>AI assistant settings | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">AI assistant</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Any OpenAI-compatible endpoint works: OpenAI, OpenRouter, a local Ollama or LM Studio server, or
                your own gateway.
            </p>
        </header>

        <x-application.settings-section title="Endpoint">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="lg:col-span-2">
                    <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Provider</label>
                    <div class="mt-1 max-h-60 overflow-auto rounded-lg border border-neutral-200 p-2 dark:border-white/[0.08]">
                        <div class="grid gap-1.5 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($providers as $entry)
                                <button type="button" wire:click="$set('provider', '{{ $entry['id'] }}')"
                                    @class([
                                        'flex items-center gap-2 rounded-lg border px-2 py-1.5 text-left text-[12px] transition',
                                        'border-dashy bg-dashy/[0.06] font-medium' => $provider === $entry['id'],
                                        'border-neutral-200 hover:bg-neutral-50 dark:border-white/[0.08] dark:hover:bg-white/[0.04]' => $provider !== $entry['id'],
                                    ])>
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-md text-[9px] font-bold text-white"
                                        style="background-color: {{ $entry['color'] }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($entry['name'], 0, 2)) }}</span>
                                    <span class="truncate">{{ $entry['name'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <p class="mt-1 text-[11px] text-neutral-500 dark:text-fg-dim">
                        {{ count($providers) }} providers. All OpenAI-compatible; OpenRouter alone exposes 300+ models.
                    </p>
                </div>
                <div>
                    <label class="text-[12px] text-neutral-500 dark:text-fg-faint" for="model">Model</label>
                    <input wire:model="model" id="model" list="ai-models" placeholder="gpt-4o-mini" spellcheck="false"
                        class="mt-1 w-full rounded-lg border border-neutral-200 bg-white p-2 font-mono text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                    <datalist id="ai-models">
                        @foreach ($models as $modelName)
                            <option value="{{ $modelName }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <x-forms.input wire:model="baseUrl" id="baseUrl" label="Base URL" placeholder="https://api.openai.com/v1" />
                <x-forms.input wire:model="apiKey" id="apiKey" type="password" label="API key" placeholder="••••••••"
                    helper="Encrypted at rest. Leave empty to keep the current key. A local Ollama needs none." />
            </div>
        </x-application.settings-section>

        <x-application.settings-section title="Behaviour">
            <div>
                <label class="text-[12px] text-neutral-500 dark:text-fg-faint">System prompt</label>
                <textarea wire:model="systemPrompt" rows="5"
                    placeholder="You are the Dashy operations assistant for a small team…"
                    class="mt-1 w-full rounded-lg border border-neutral-200 bg-white p-3 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]"></textarea>
            </div>
            <label class="mt-4 flex items-start gap-2 text-[13px]">
                <input type="checkbox" wire:model="allowCommandExecution" class="mt-0.5 rounded border-neutral-300 dark:border-white/20">
                <span>
                    Allow team admins to run a proposed command on a server.
                    <span class="block text-[12px] text-neutral-500 dark:text-fg-dim">
                        The assistant only proposes the command; nothing runs until an admin presses Run.
                    </span>
                </span>
            </label>
        </x-application.settings-section>

        <div class="flex flex-wrap gap-2">
            <x-forms.button type="button" wire:click="save" isHighlighted>Save assistant settings</x-forms.button>
            <a href="{{ route('assistant.index') }}" class="button h-8! px-3! text-[12px]!">Open the assistant</a>
        </div>
    </div>
</div>
