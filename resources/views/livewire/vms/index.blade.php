<div>
    <x-slot:title>
        Virtual machines | Dashy
    </x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Virtual machines</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Create VMs on Proxmox VE from cloud images, installer ISOs or templates. Cloud images are
                imported, booted and configured over cloud-init automatically.
            </p>
        </header>

        @if (! $configured)
            <div class="rounded-lg border border-warning/40 bg-warning/10 p-4 text-[12px]">
                Proxmox is not configured yet. Set <code>PROXMOX_URL</code>, <code>PROXMOX_TOKEN_ID</code> and
                <code>PROXMOX_TOKEN_SECRET</code>, then reload this page. The token needs VM.Allocate,
                VM.Config.*, Datastore.AllocateSpace and Datastore.Audit on the node and storage you use.
            </div>
        @endif

        @if ($notice)
            <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-[12px]">{{ $notice }}</div>
        @endif

        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px] break-words">{{ $error }}</div>
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
                    @error('os')
                        <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                    @enderror
                </div>

                <x-forms.input id="name" label="VM name" placeholder="my-server" />
                <x-forms.input id="vmid" label="VM ID (optional)" placeholder="next free id" />

                <x-forms.input id="cores" type="number" label="vCPU" min="1" max="16" />
                <x-forms.input id="memory" type="number" label="Memory (MB)" min="256" max="65536" />
                <x-forms.input id="disk" type="number" label="Disk (GB)" min="2" max="500" />

                <x-forms.input id="node" label="Proxmox node" />
                <x-forms.input id="storage" label="Disk storage" />
                <x-forms.input id="bridge" label="Network bridge" />
                <x-forms.input id="isoStorage" label="ISO storage" />
            </div>

            @if (($selected['kind'] ?? 'cloud') === 'iso')
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <x-forms.input id="iso" label="ISO volume" placeholder="local:iso/windows-server-2022.iso"
                        helper="Use an ISO already uploaded to Proxmox ISO storage." />
                    <x-forms.input id="password" type="password" label="Initial password (optional)"
                        helper="Windows Setup still needs an answer file for a fully unattended install." />
                </div>
                @if (! empty($selected['note']))
                    <p class="mt-2 text-[12px] text-warning">{{ $selected['note'] }}</p>
                @endif
            @else
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <x-forms.input id="imageUrl" label="Custom image URL (optional)"
                        placeholder="https://example.com/image.qcow2"
                        helper="Overrides the catalog URL. Any HTTPS qcow2/raw image works." />
                    <x-forms.input id="password" type="password" label="Cloud-init password (optional)" />
                </div>
                <div class="mt-4">
                    <label for="sshKeys" class="mb-1 block text-[12px] font-medium">SSH public keys</label>
                    <textarea id="sshKeys" wire:model="sshKeys" rows="3"
                        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 font-mono text-[12px] dark:border-white/[0.08] dark:bg-white/[0.03]"
                        placeholder="ssh-ed25519 AAAA... user@host"></textarea>
                    @error('sshKeys')
                        <p class="mt-1 text-[12px] text-error">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="mt-4 flex items-center gap-3">
                <x-forms.button type="button" wire:click="provision" wire:loading.attr="disabled"
                    wire:target="provision">
                    <span wire:loading.remove wire:target="provision">Create VM</span>
                    <span wire:loading wire:target="provision">Provisioning…</span>
                </x-forms.button>
                <span class="text-[12px] text-neutral-500 dark:text-fg-dim">
                    Importing a cloud image can take a few minutes.
                </span>
            </div>
        </x-application.settings-section>

        <x-application.settings-section title="VMs on {{ $node }}">
            <div class="mb-3 flex items-center justify-between">
                <p class="text-[12px] text-neutral-500 dark:text-fg-dim">
                    {{ count($vms) }} VM(s) reported by Proxmox.
                </p>
                <x-forms.button type="button" wire:click="loadVms">Refresh</x-forms.button>
            </div>

            @if ($vms === [])
                <p class="text-[12px] text-neutral-500 dark:text-fg-dim">No VMs found on this node.</p>
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
                                @php $vmid = (int) ($vm['vmid'] ?? 0); @endphp
                                <tr class="border-t border-neutral-100 dark:border-white/[0.05]">
                                    <td class="px-3 py-2 font-mono">{{ $vmid }}</td>
                                    <td class="px-3 py-2">{{ $vm['name'] ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="{{ ($vm['status'] ?? '') === 'running' ? 'text-success' : 'text-neutral-500' }}">
                                            {{ $vm['status'] ?? 'unknown' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ $vm['cpus'] ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        {{ isset($vm['maxmem']) ? round(((int) $vm['maxmem']) / 1073741824, 1).' GB' : '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ isset($vm['maxdisk']) ? round(((int) $vm['maxdisk']) / 1073741824, 1).' GB' : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" wire:click="start({{ $vmid }})"
                                            class="button h-7! px-2! text-[11px]!">Start</button>
                                        <button type="button" wire:click="shutdown({{ $vmid }})"
                                            wire:confirm="Shut down VM {{ $vmid }}?"
                                            class="button h-7! px-2! text-[11px]!">Shutdown</button>
                                        <button type="button" wire:click="deleteVm({{ $vmid }})"
                                            wire:confirm="Delete VM {{ $vmid }} and its disks?"
                                            class="button h-7! px-2! text-[11px]! text-error">Delete</button>
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
