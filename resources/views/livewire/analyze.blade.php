<div>
    <x-slot:title>Analyze a project | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Analyze a project</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Point at a public GitHub repository or drop a zip. Dashy detects the language, framework,
                build pack, port and the commands to run.
            </p>
        </header>

        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px]">{{ $error }}</div>
        @endif

        <x-application.settings-section title="Source">
            <div class="grid items-end gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <x-forms.input wire:model="url" id="url" label="GitHub repository"
                        placeholder="https://github.com/owner/repo" />
                </div>
                <x-forms.button type="button" wire:click="analyzeGithub" isHighlighted>Analyze repository</x-forms.button>
            </div>

            <div class="mt-6 grid items-end gap-4 border-t border-neutral-100 pt-6 lg:grid-cols-3 dark:border-white/[0.05]">
                <div class="lg:col-span-2">
                    <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Or a zip archive (max 50 MB)</label>
                    <input type="file" wire:model="zip" accept=".zip"
                        class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08]">
                    <div wire:loading wire:target="zip" class="mt-1 text-[11px] text-neutral-500">Uploading…</div>
                </div>
                <x-forms.button type="button" wire:click="analyzeZip">Analyze archive</x-forms.button>
            </div>
        </x-application.settings-section>

        @if ($result)
            <x-application.settings-section title="Result">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        'Language' => $result['language'],
                        'Framework' => $result['framework'] ?? '—',
                        'Build pack' => $result['build_pack'],
                        'Port' => (string) $result['port'],
                        'Confidence' => $result['confidence'],
                        'Files scanned' => (string) $result['files_scanned'],
                    ] as $label => $value)
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <p class="text-[10px] uppercase tracking-wide text-neutral-500 dark:text-fg-faint">{{ $label }}</p>
                            <p class="mt-0.5 text-[14px] font-semibold">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="mb-1 text-[12px] font-medium">Commands</p>
                        <pre class="overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">install: {{ $result['install'] ?? '—' }}
build:   {{ $result['build'] ?? '—' }}
start:   {{ $result['start'] ?? '—' }}</pre>
                    </div>
                    <div>
                        <p class="mb-1 text-[12px] font-medium">Suggested environment</p>
                        <pre class="overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $result['env'] === [] ? '—' : collect($result['env'])->map(fn ($value, $key) => $key.'='.$value)->implode("\n") }}</pre>
                    </div>
                </div>

                @if ($result['notes'] !== [])
                    <ul class="mt-4 list-disc pl-5 text-[12px] text-neutral-600 dark:text-fg-dim">
                        @foreach ($result['notes'] as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-4 flex gap-2">
                    <a href="{{ route('project.index') }}" class="button h-8! px-3! text-[12px]!">Create a project with this</a>
                    <button type="button" class="button h-8! px-3! text-[12px]!" wire:click="resetAnalysis">Clear</button>
                </div>
            </x-application.settings-section>
        @endif
    </div>
</div>
