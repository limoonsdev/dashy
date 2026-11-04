<div>
    <x-slot:title>Dev environments | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Dev environments</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Spin up a browser IDE or a local AI studio on any server. Copy the command and run it from the
                server terminal.
            </p>
        </header>

        <x-application.settings-section title="Template">
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($environments as $key => $environment)
                    <button type="button" wire:click="setEnvironment('{{ $key }}')"
                        @class([
                            'rounded-[10px] border p-4 text-left transition-colors',
                            'border-dashy ring-2 ring-dashy/25' => $environment === $key,
                            'border-neutral-200 hover:border-neutral-300 dark:border-white/[0.08]' => $environment !== $key,
                        ])>
                        <span class="block text-[13px] font-semibold">{{ $environment['label'] }}</span>
                        <span class="mt-1 block text-[12px] text-neutral-500 dark:text-fg-dim">{{ $environment['description'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="mt-4 grid items-end gap-4 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Server</label>
                    <select wire:model="serverUuid" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                        @foreach ($servers as $server)
                            <option value="{{ $server->uuid }}">{{ $server->name }}</option>
                        @endforeach
                    </select>
                </div>
                <x-forms.input wire:model.live="port" id="port" type="number" label="Published port" />
                <x-forms.input wire:model.live="memory" id="memory" type="number" label="Memory limit (MB)" />
            </div>

            <div class="mt-4">
                <x-forms.input wire:model.live="password" id="password" type="password" label="Password"
                    helper="Used by Code Server. AI Studio uses its own first-run account." />
            </div>
        </x-application.settings-section>

        <x-application.settings-section title="Command">
            <pre class="overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $command }}</pre>
            <p class="mt-3 text-[12px] text-neutral-500 dark:text-fg-dim">
                The container keeps its data in a named volume, so restarts and updates keep your work.
                Code Server is reachable on port {{ $port }}; put it behind your proxy with HTTPS.
            </p>
        </x-application.settings-section>
    </div>
</div>
