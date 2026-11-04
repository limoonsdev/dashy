<div>
    <x-slot:title>Virtual machines | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Virtual machines</h1>
                <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                    One page, two providers. Pick Proxmox VE or Incus, or let Auto Config ask four questions
                    and fill every setting for you.
                </p>
            </div>
            <button type="button" wire:click="openWizard"
                class="button h-9! gap-1.5! px-3! text-[12px]!">
                <x-reicon name="sparkles" class="size-3.5" />
                Auto Config
            </button>
        </header>

        <div wire:poll.15s="refreshStatus" class="grid gap-3 sm:grid-cols-2">
            @foreach ($providerLabels as $key => $label)
                <button type="button" wire:click="setProvider('{{ $key }}')"
                    @class([
                        'flex items-center justify-between gap-2 rounded-[10px] border px-3 py-2.5 text-left transition-colors',
                        'border-dashy ring-2 ring-dashy/25' => $provider === $key,
                        'border-neutral-200 hover:border-neutral-300 dark:border-white/[0.08]' => $provider !== $key,
                    ])>
                    <span class="flex items-center gap-2">
                        <x-reicon name="servers" class="size-4" />
                        <span class="text-[13px] font-semibold">{{ $label }}</span>
                    </span>
                    <span class="flex items-center gap-1.5 text-[11px]">
                        <span class="size-1.5 rounded-full {{ ($providers[$key] ?? false) ? 'bg-success' : 'bg-warning' }}"></span>
                        {{ ($providers[$key] ?? false) ? 'connected' : 'not configured' }}
                    </span>
                </button>
            @endforeach
        </div>

        @if (! $configured)
            <div class="rounded-lg border border-warning/40 bg-warning/10 p-4 text-[12px]">
                @if ($provider === 'incus')
                    Set <code>INCUS_URL</code>, <code>INCUS_TOKEN</code> (or certificate) and optionally
                    <code>INCUS_PROJECT</code>, then reload. Images are pulled from the simplestreams remote,
                    so no ISO storage is needed.
                @else
                    Set <code>PROXMOX_URL</code>, <code>PROXMOX_TOKEN_ID</code> and
                    <code>PROXMOX_TOKEN_SECRET</code>, then reload. The token needs VM.Allocate, VM.Config.*,
                    Datastore.AllocateSpace and Datastore.Audit on the node and storage you use.
                @endif
            </div>
        @endif

        @if ($notice)
            <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-[12px]">{{ $notice }}</div>
        @endif

        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px] break-words">{{ $error }}</div>
        @endif

        @if ($wizardOpen)
            <x-application.settings-section title="Auto Config · step {{ $wizardStep }} of 4">
                <div class="mb-4 flex gap-1">
                    @for ($step = 1; $step <= 4; $step++)
                        <span @class([
                            'h-1 flex-1 rounded-full',
                            'bg-dashy' => $step <= $wizardStep,
                            'bg-neutral-200 dark:bg-white/10' => $step > $wizardStep,
                        ])></span>
                    @endfor
                </div>

                @if ($wizardStep === 1)
                    <p class="mb-3 text-[13px]">Where should the machine run?</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($providerLabels as $key => $label)
                            <label @class([
                                'cursor-pointer rounded-lg border p-3',
                                'border-dashy ring-2 ring-dashy/25' => $answers['provider'] === $key,
                                'border-neutral-200 dark:border-white/[0.08]' => $answers['provider'] !== $key,
                                'opacity-50' => ! ($providers[$key] ?? false),
                            ])>
                                <input type="radio" value="{{ $key }}" wire:model="answers.provider"
                                    @disabled(! ($providers[$key] ?? false)) class="mr-1.5">
                                <span class="text-[13px] font-semibold">{{ $label }}</span>
                                <span class="mt-0.5 block text-[11px] text-neutral-500 dark:text-fg-dim">
                                    {{ ($providers[$key] ?? false) ? 'Connected, ready to create.' : 'Set the credentials in the environment first.' }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                @elseif ($wizardStep === 2)
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Machine name</label>
                            <input wire:model="answers.name"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.03]">
                            @error('answers.name') <p class="mt-1 text-[11px] text-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Operating system</label>
                            <select wire:model.live="answers.os"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.03]">
                                @foreach ($catalog as $key => $entry)
                                    <option value="{{ $key }}">{{ $entry['label'] ?? $key }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-neutral-500 dark:text-fg-dim">
                        {{ ($answers['provider'] ?? '') === 'incus'
                            ? 'Incus pulls the image named in INCUS_IMAGE; the OS choice only sets the defaults.'
                            : 'Cloud images are imported and configured over cloud-init automatically.' }}
                    </p>
                @elseif ($wizardStep === 3)
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="text-[12px] text-neutral-500 dark:text-fg-faint">vCPU</label>
                            <input type="number" min="1" max="16" wire:model="answers.cores"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.03]">
                        </div>
                        <div>
                            <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Memory (MB)</label>
                            <input type="number" min="256" step="256" wire:model="answers.memory"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.03]">
                        </div>
                        <div>
                            <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Disk (GB)</label>
                            <input type="number" min="2" max="500" wire:model="answers.disk"
                                class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.03]">
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-neutral-500 dark:text-fg-dim">
                        Total: {{ (int) $answers['cores'] }} vCPU · {{ (int) $answers['memory'] }} MB RAM ·
                        {{ (int) $answers['disk'] }} GB disk.
                    </p>
                @else
                    <div>
                        <label class="text-[12px] text-neutral-500 dark:text-fg-faint">SSH public keys (optional)</label>
                        <textarea wire:model="answers.sshKeys" rows="3"
                            class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 font-mono text-[12px] dark:border-white/[0.08] dark:bg-white/[0.03]"
                            placeholder="ssh-ed25519 AAAA... user@host"></textarea>
                    </div>
                    <div class="mt-3 rounded-lg border border-neutral-200 p-3 text-[12px] dark:border-white/[0.08]">
                        <p><span class="text-neutral-500 dark:text-fg-faint">Provider:</span> {{ $providerLabels[$answers['provider']] ?? '' }}</p>
                        <p><span class="text-neutral-500 dark:text-fg-faint">Name:</span> {{ $answers['name'] }}</p>
                        <p><span class="text-neutral-500 dark:text-fg-faint">OS:</span> {{ $catalog[$answers['os']]['label'] ?? $answers['os'] }}</p>
                        <p><span class="text-neutral-500 dark:text-fg-faint">Size:</span> {{ $answers['cores'] }} vCPU / {{ $answers['memory'] }} MB / {{ $answers['disk'] }} GB</p>
                    </div>
                @endif

                <div class="mt-4 flex items-center gap-2">
                    @if ($wizardStep > 1)
                        <button type="button" class="button h-8! px-3! text-[12px]!" wire:click="previousStep">Back</button>
                    @endif
                    @if ($wizardStep < 4)
                        <x-forms.button type="button" wire:click="nextStep" isHighlighted>Continue</x-forms.button>
                    @else
                        <x-forms.button type="button" wire:click="applyWizard" wire:loading.attr="disabled" wire:target="applyWizard">
                            <span wire:loading.remove wire:target="applyWizard">Create with these settings</span>
                            <span wire:loading wire:target="applyWizard">Provisioning…</span>
                        </x-forms.button>
                    @endif
                    <button type="button" class="text-[12px] text-neutral-500 underline" wire:click="$set('wizardOpen', false)">Cancel</button>
                </div>
            </x-application.settings-section>
        @endif

        <x-application.settings-section title="Create a VM">
            <div class="grid items-end gap-4 lg:grid-cols-3">
                <div>
                    <label for="os" class="mb-1 block text-[12px] font-medium">Operating system</label>
                    <select id="os" wire:model.live="os"
                        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.03]">
                        @foreach ($catalog as $key => $entry)
                            <option value="{{ $key }}">{{ $entry['label'] ?? $key }}</option>
                        @endforeach
                    </select>
                    @error('os') <p class="mt-1 text-[12px] text-error">{{ $message }}</p> @enderror
                </div>
                <x-forms.input id="name" label="VM name" placeholder="my-server" />
                <x-forms.input id="cores" type="number" label="vCPU" min="1" max="16" />
                <x-forms.input id="memory" type="number" label="Memory (MB)" min="256" max="65536" />
                <x-forms.input id="disk" type="number" label="Disk (GB)" min="2" max="500" />
                <x-forms.input id="vmid" label="VM ID (optional)" placeholder="next free id" />
            </div>

            @if ($provider === 'proxmox')
                <div class="mt-4 grid items-end gap-4 lg:grid-cols-4">
                    <x-forms.input id="node" label="Proxmox node" />
                    <x-forms.input id="storage" label="Disk storage" />
                    <x-forms.input id="bridge" label="Network bridge" />
                    <x-forms.input id="isoStorage" label="ISO storage" />
                </div>
            @else
                <p class="mt-3 text-[12px] text-neutral-500 dark:text-fg-dim">
                    Incus uses <code>INCUS_STORAGE</code>, <code>INCUS_NETWORK</code> and <code>INCUS_IMAGE</code>
                    from the environment. Private keys are injected as a cloud-init user.
                </p>
            @endif

            @if (($selected['kind'] ?? 'cloud') === 'iso' && $provider === 'proxmox')
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <x-forms.input id="iso" label="ISO volume" placeholder="local:iso/windows-server-2022.iso" />
                    <x-forms.input id="password" type="password" label="Initial password (optional)" />
                </div>
            @else
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <x-forms.input id="imageUrl" label="Custom image URL (optional)" placeholder="https://example.com/image.qcow2"
                        helper="Overrides the catalog URL. Any HTTPS qcow2/raw image works." />
                    <x-forms.input id="password" type="password" label="Cloud-init password (optional)" />
                </div>
                <div class="mt-4">
                    <label for="sshKeys" class="mb-1 block text-[12px] font-medium">SSH public keys</label>
                    <textarea id="sshKeys" wire:model="sshKeys" rows="3"
                        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 font-mono text-[12px] dark:border-white/[0.08] dark:bg-white/[0.03]"
                        placeholder="ssh-ed25519 AAAA... user@host"></textarea>
                </div>
            @endif

            <div class="mt-4 flex items-center gap-3">
                <x-forms.button type="button" wire:click="provision" wire:loading.attr="disabled" wire:target="provision">
                    <span wire:loading.remove wire:target="provision">Create VM</span>
                    <span wire:loading wire:target="provision">Provisioning…</span>
                </x-forms.button>
                <span class="text-[12px] text-neutral-500 dark:text-fg-dim">Importing an image can take a few minutes.</span>
            </div>
        </x-application.settings-section>

        <x-application.settings-section title="Machines on {{ $provider === 'incus' ? 'Incus' : $node }}">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-[12px] text-neutral-500 dark:text-fg-dim">
                    {{ count($vms) }} machine(s) · refreshed every 15 s
                </p>
                <x-forms.button type="button" wire:click="loadVms">Refresh</x-forms.button>
            </div>

            @if ($vms === [])
                <p class="text-[12px] text-neutral-500 dark:text-fg-dim">No machines found.</p>
            @else
                <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-white/[0.08]">
                    <table class="w-full text-left text-[12px]">
                        <thead class="bg-neutral-50 text-[11px] text-neutral-500 dark:bg-white/[0.03] dark:text-fg-faint">
                            <tr>
                                <th class="px-3 py-2 font-medium">ID</th>
                                <th class="px-3 py-2 font-medium">Name</th>
                                <th class="px-3 py-2 font-medium">Status</th>
                                <th class="px-3 py-2 font-medium">vCPU</th>
                                <th class="px-3 py-2 font-medium">Memory</th>
                                <th class="px-3 py-2 font-medium">Disk</th>
                                <th class="px-3 py-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vms as $vm)
                                <tr class="border-t border-neutral-100 dark:border-white/[0.05]">
                                    <td class="px-3 py-2 font-mono">{{ $vm['id'] }}</td>
                                    <td class="px-3 py-2">{{ $vm['name'] }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="size-1.5 rounded-full {{ $vm['status'] === 'running' ? 'bg-success' : 'bg-neutral-400' }}"></span>
                                            {{ $vm['status'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ $vm['cpus'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $vm['memory'] ? round($vm['memory'] / 1073741824, 1).' GB' : '—' }}</td>
                                    <td class="px-3 py-2">{{ $vm['disk'] ? round($vm['disk'] / 1073741824, 1).' GB' : '—' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" wire:click="start('{{ $vm['id'] }}')" class="button h-7! px-2! text-[11px]!">Start</button>
                                        <button type="button" wire:click="shutdown('{{ $vm['id'] }}')" wire:confirm="Shut down {{ $vm['name'] }}?" class="button h-7! px-2! text-[11px]!">Shutdown</button>
                                        <button type="button" wire:click="deleteVm('{{ $vm['id'] }}')" wire:confirm="Delete {{ $vm['name'] }} and its disks?" class="button h-7! px-2! text-[11px]! text-error">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-application.settings-section>
    </div>
</div>
