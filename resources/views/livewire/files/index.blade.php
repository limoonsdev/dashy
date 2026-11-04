<div>
    <x-slot:title>Files | Dashy</x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">File manager</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Browse and edit files on a server over SSH. Paths are absolute and every action runs as the
                server user.
            </p>
        </header>

        @if ($error)
            <div class="rounded-lg border border-error/40 bg-error/10 p-3 text-[12px]">{{ $error }}</div>
        @endif
        @if ($notice)
            <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-[12px]">{{ $notice }}</div>
        @endif

        <x-application.settings-section title="Location">
            <div class="grid items-end gap-4 lg:grid-cols-4">
                <div class="lg:col-span-1">
                    <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Server</label>
                    <select wire:model.live="serverUuid" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                        @foreach ($servers as $server)
                            <option value="{{ $server->uuid }}">{{ $server->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <x-forms.input wire:model="path" id="path" label="Path" placeholder="/var/www" />
                </div>
                <div class="flex gap-2">
                    <x-forms.button type="button" wire:click="go">Open</x-forms.button>
                    <x-forms.button type="button" wire:click="up">Up</x-forms.button>
                </div>
            </div>
        </x-application.settings-section>

        <x-application.settings-section title="Contents">
            <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-white/[0.08]">
                <table class="w-full text-left text-[12px]">
                    <thead class="bg-neutral-50 text-[11px] text-neutral-500 dark:bg-white/[0.03] dark:text-fg-faint">
                        <tr>
                            <th class="px-3 py-2 font-medium">Name</th>
                            <th class="px-3 py-2 font-medium">Size</th>
                            <th class="px-3 py-2 font-medium">Mode</th>
                            <th class="px-3 py-2 font-medium">Owner</th>
                            <th class="px-3 py-2 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr class="border-t border-neutral-100 dark:border-white/[0.05]">
                                <td class="px-3 py-2">
                                    <button type="button" wire:click="open('{{ $entry['path'] }}')" class="font-mono hover:underline">
                                        {{ $entry['type'] === 'dir' ? '📁' : '📄' }} {{ $entry['name'] }}
                                    </button>
                                </td>
                                <td class="px-3 py-2 text-neutral-500 dark:text-fg-dim">
                                    {{ $entry['type'] === 'dir' ? '—' : number_format($entry['size'] / 1024, 1).' KB' }}
                                </td>
                                <td class="px-3 py-2 font-mono text-[11px]">{{ $entry['perms'] }}</td>
                                <td class="px-3 py-2 text-neutral-500 dark:text-fg-dim">{{ $entry['owner'] }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button" class="text-[11px] hover:underline" wire:click="compress('{{ $entry['path'] }}')">Archive</button>
                                    <span class="px-1 text-neutral-300 dark:text-white/20">·</span>
                                    <button type="button" class="text-[11px] text-error hover:underline"
                                        wire:click="remove('{{ $entry['path'] }}')"
                                        wire:confirm="Delete {{ $entry['name'] }}?">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-6 text-center text-neutral-500 dark:text-fg-dim">This folder is empty.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 grid items-end gap-4 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <x-forms.input wire:model="newName" id="newName" label="New file or folder name" placeholder="notes.txt" />
                </div>
                <div>
                    <x-forms.input wire:model="chmod" id="chmod" label="Permissions" placeholder="644" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-forms.button type="button" wire:click="create">New file</x-forms.button>
                    <x-forms.button type="button" wire:click="makeDirectory">New folder</x-forms.button>
                </div>
            </div>
        </x-application.settings-section>

        @if ($editingPath)
            <x-application.settings-section title="Editing {{ $editingPath }}">
                <textarea wire:model="content" rows="18" spellcheck="false"
                    class="w-full rounded-lg border border-neutral-200 bg-white p-3 font-mono text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]"></textarea>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-forms.button type="button" wire:click="save" isHighlighted>Save file</x-forms.button>
                    <x-forms.button type="button" wire:click="applyChmod('{{ $editingPath }}')">Apply permissions</x-forms.button>
                    <button type="button" class="button h-8! px-3! text-[12px]!" wire:click="$set('editingPath', null)">Close</button>
                </div>
            </x-application.settings-section>
        @endif
    </div>
</div>
