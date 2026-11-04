<div>
    <x-slot:title>
        Toolkit | Dashy
    </x-slot:title>

    <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
        <header>
            <h1 class="text-[24px]! leading-7! font-semibold! tracking-tight!">Toolkit</h1>
            <p class="mt-1 text-[13px] text-neutral-500 dark:text-fg-dim">
                Domain intelligence, archive import, database URLs and server hardening in one place.
            </p>
        </header>

        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Toolkit sections">
            @foreach (['domains' => 'Domains', 'import' => 'Import', 'database' => 'Database', 'hardening' => 'Hardening', 'security' => 'Security', 'mods' => 'Mods', 'vm' => 'VM toolkit'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    class="button h-8! px-3! text-[12px]! {{ $tab === $key ? 'button-highlighted' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($tab === 'domains')
            <x-application.settings-section title="Domain lookup">
                <div class="grid items-end gap-4 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <x-forms.input id="domainQuery" label="Domain" placeholder="example.com"
                            helper="RDAP is queried first because it is structured and HTTPS. WHOIS is the port 43 fallback." />
                    </div>
                    <div class="flex gap-2">
                        <x-forms.button type="button" wire:click="lookupDomain">Look up</x-forms.button>
                        <x-forms.button type="button" wire:click="lookupWhois">WHOIS</x-forms.button>
                    </div>
                </div>

                @if (! empty($domainResult))
                    <dl class="mt-4 grid gap-3 text-[13px] sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Status</dt>
                            <dd class="mt-1 font-semibold">{{ ucfirst($domainResult['status'] ?? 'unknown') }}</dd>
                            @if (! empty($domainResult['error']))
                                <dd class="mt-1 text-[12px] text-error">{{ $domainResult['error'] }}</dd>
                            @endif
                        </div>
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Registrar</dt>
                            <dd class="mt-1 font-semibold">{{ $domainResult['registrar'] ?? 'Unknown' }}</dd>
                        </div>
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Registered</dt>
                            <dd class="mt-1 font-semibold">{{ $domainResult['created'] ?? 'Unknown' }}</dd>
                        </div>
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Expires</dt>
                            <dd class="mt-1 font-semibold">{{ $domainResult['expires'] ?? 'Unknown' }}</dd>
                        </div>
                    </dl>

                    @if (! empty($domainResult['nameservers']))
                        <p class="mt-3 text-[12px] text-neutral-500 dark:text-fg-dim">
                            Nameservers: {{ implode(', ', $domainResult['nameservers']) }}
                        </p>
                    @endif
                @endif

                @if ($embeddedIp)
                    <div class="mt-4 rounded-lg border border-warning/40 bg-warning/10 p-3 text-[12px]">
                        This hostname publishes <strong>{{ $embeddedIp }}</strong> inside the name itself.
                        sslip.io, nip.io and xip.io work by reading the address from the label, so anyone who
                        sees the URL learns the server IP. Use a random label on a domain you own when the
                        address must stay private.
                    </div>
                @endif

                @if ($dnsRecords !== [])
                    <div class="mt-4 overflow-hidden rounded-lg border border-neutral-200 dark:border-white/[0.08]">
                        <table class="w-full text-left text-[12px]">
                            <thead class="bg-neutral-50 text-[11px] text-neutral-500 dark:bg-white/[0.03] dark:text-fg-faint">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Type</th>
                                    <th class="px-3 py-2 font-medium">Value</th>
                                    <th class="px-3 py-2 font-medium">TTL</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dnsRecords as $record)
                                    <tr class="border-t border-neutral-100 dark:border-white/[0.05]">
                                        <td class="px-3 py-2 font-mono">{{ $record['type'] }}</td>
                                        <td class="px-3 py-2 font-mono break-all">{{ $record['value'] }}</td>
                                        <td class="px-3 py-2 text-neutral-500 dark:text-fg-faint">{{ $record['ttl'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (! empty($whoisResult))
                    <pre class="mt-4 max-h-72 overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $whoisResult['raw'] ?? ($whoisResult['error'] ?? 'No record returned.') }}</pre>
                @endif
            </x-application.settings-section>

            <x-application.settings-section title="Name ideas and TLD check">
                <div class="grid items-end gap-4 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <x-forms.input id="siteLabel" label="Project name" placeholder="coffee-shop"
                            helper="Letters, numbers and dashes only. The generator never embeds the server IP." />
                    </div>
                    <div class="flex gap-2">
                        <x-forms.button type="button" wire:click="searchDomains">Check TLDs</x-forms.button>
                        <x-forms.button type="button" wire:click="generateHostname">Generate host</x-forms.button>
                    </div>
                </div>

                @if ($generatedHost)
                    <div class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                        <div class="text-[11px] text-neutral-500 dark:text-fg-faint">Suggested hostname</div>
                        <div class="mt-1 flex items-center gap-2">
                            <code class="flex-1 font-mono text-[13px] break-all">{{ $generatedHost }}</code>
                            <button type="button" class="button h-8! px-2.5! text-[12px]!"
                                x-on:click="navigator.clipboard.writeText(@js($generatedHost))">Copy</button>
                        </div>
                        <p class="mt-2 text-[12px] text-neutral-500 dark:text-fg-dim">{{ $dnsInstruction }}</p>
                    </div>
                @endif

                @if ($tldResults !== [])
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($tldResults as $result)
                            <span @class([
                                'rounded-full border px-2.5 py-1 text-[12px]',
                                'border-success/40 bg-success/10 text-success' => $result['status'] === 'available',
                                'border-neutral-200 text-neutral-600 dark:border-white/[0.08] dark:text-fg-dim' => $result['status'] === 'registered',
                                'border-warning/40 bg-warning/10' => ! in_array($result['status'], ['available', 'registered'], true),
                            ])>
                                {{ $result['domain'] }} · {{ $result['status'] }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </x-application.settings-section>
        @endif

        @if ($tab === 'import')
            <x-application.settings-section title="Import a zip archive">
                <div class="flex flex-col gap-3">
                    <input type="file" wire:model="archive" accept=".zip,application/zip"
                        class="w-full rounded-lg border border-neutral-200 bg-white p-3 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.03] dark:text-fg">
                    <div class="flex items-center gap-3">
                        <x-forms.button type="button" wire:click="importArchive">Extract and inspect</x-forms.button>
                        <span wire:loading wire:target="archive,importArchive" class="text-[12px] text-neutral-500 dark:text-fg-faint">Working…</span>
                    </div>
                    @error('archive')
                        <p class="text-[12px] text-error">{{ $message }}</p>
                    @enderror
                    <p class="text-[12px] text-neutral-500 dark:text-fg-dim">
                        Extraction rejects path traversal, absolute paths and symlinks, and caps the archive at
                        20,000 files / 512 MB uncompressed.
                    </p>
                    <p class="text-[12px] {{ $malwareScanningEnabled ? 'text-success' : 'text-warning' }}">
                        Malware scanning:
                        {{ $malwareScanningEnabled ? ($malwareScanningRequired ? 'required' : 'enabled') : 'disabled (set CLAMAV_HOST)' }}.
                        ClamAV is signature based, so it complements the path and size checks above.
                    </p>
                </div>

                @if (! empty($importReport))
                    <div class="mt-5 grid gap-3 text-[13px] sm:grid-cols-3">
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <div class="text-[11px] text-neutral-500 dark:text-fg-faint">Files</div>
                            <div class="mt-1 font-semibold">{{ $importReport['files'] }}</div>
                        </div>
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <div class="text-[11px] text-neutral-500 dark:text-fg-faint">Uncompressed</div>
                            <div class="mt-1 font-semibold">{{ round(($importReport['bytes'] ?? 0) / 1048576, 1) }} MB</div>
                        </div>
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <div class="text-[11px] text-neutral-500 dark:text-fg-faint">Build pack</div>
                            <div class="mt-1 font-semibold">{{ $importReport['stack']['buildpack'] ?? 'none' }}</div>
                        </div>
                    </div>

                    <dl class="mt-3 grid gap-3 text-[13px] sm:grid-cols-3">
                        <div>
                            <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Runtime</dt>
                            <dd class="mt-1 font-semibold">{{ $importReport['stack']['runtime'] ?? 'unknown' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Framework</dt>
                            <dd class="mt-1 font-semibold">{{ $importReport['stack']['framework'] ?? 'Unknown' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-neutral-500 dark:text-fg-faint">Database</dt>
                            <dd class="mt-1 font-semibold">
                                {{ $importReport['stack']['needs_database'] ?? false ? ($importReport['stack']['database_engine'] ?? 'Required') : 'Not required' }}
                            </dd>
                        </div>
                    </dl>

                    @if (! empty($importReport['stack']['notes']))
                        <ul class="mt-4 list-disc space-y-1 pl-5 text-[12px] text-neutral-600 dark:text-fg-dim">
                            @foreach ($importReport['stack']['notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <code class="text-[11px] break-all text-neutral-500 dark:text-fg-faint">{{ $importReport['path'] ?? '' }}</code>
                        <button type="button" class="button h-8! px-2.5! text-[12px]!" wire:click="discardImport">Discard files</button>
                    </div>
                @endif
            </x-application.settings-section>
        @endif

        @if ($tab === 'database')
            <x-application.settings-section title="Connection strings">
                @if ($databases === [])
                    <p class="text-[13px] text-neutral-500 dark:text-fg-dim">
                        No PostgreSQL database in this team yet. Create one from a project, then come back.
                    </p>
                @else
                    <div class="grid gap-4 lg:grid-cols-3">
                        <label class="flex flex-col gap-1 text-[12px]">
                            <span class="text-neutral-500 dark:text-fg-faint">Database</span>
                            <select wire:model="selectedDatabase"
                                class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-[14px] dark:border-white/[0.08] dark:bg-white/[0.03] dark:text-fg">
                                @foreach ($databases as $database)
                                    <option value="{{ $database['uuid'] }}">{{ $database['name'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="flex flex-col gap-1 text-[12px]">
                            <span class="text-neutral-500 dark:text-fg-faint">Target</span>
                            <select wire:model="connectionTarget"
                                class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-[14px] dark:border-white/[0.08] dark:bg-white/[0.03] dark:text-fg">
                                @foreach (\App\Services\Toolkit\DatabaseUrl::TARGETS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="flex flex-col gap-1 text-[12px]">
                            <span class="text-neutral-500 dark:text-fg-faint">Network</span>
                            <select wire:model="externalConnection"
                                class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-[14px] dark:border-white/[0.08] dark:bg-white/[0.03] dark:text-fg">
                                <option value="0">Internal (container network)</option>
                                <option value="1">Public (server address and port)</option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-4">
                        <x-forms.button type="button" wire:click="buildConnectionString">Build URL</x-forms.button>
                    </div>

                    @error('connectionString')
                        <p class="mt-3 text-[12px] text-error">{{ $message }}</p>
                    @enderror

                    @if ($connectionString)
                        <div class="mt-4 rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <div class="text-[11px] text-neutral-500 dark:text-fg-faint">Connection string</div>
                            <div class="mt-1 flex items-start gap-2">
                                <code x-ref="connection" class="flex-1 font-mono text-[12px] break-all">{{ $connectionString }}</code>
                                <button type="button" class="button h-8! px-2.5! text-[12px]!"
                                    x-on:click="navigator.clipboard.writeText($refs.connection.textContent)">Copy</button>
                            </div>
                            <p class="mt-2 text-[12px] text-warning">
                                This URL contains the database password. Share it only with the app that needs it.
                            </p>
                        </div>
                    @endif
                @endif
            </x-application.settings-section>
        @endif

        @if ($tab === 'hardening')
            <x-application.settings-section title="Server hardening script">
                <div class="grid gap-4 lg:grid-cols-4">
                    <div>
                        <x-forms.input id="sshPort" type="number" label="SSH port" helper="Rate limited in UFW." />
                    </div>
                    <div class="lg:col-span-3">
                        <span class="text-[12px] text-neutral-500 dark:text-fg-faint">Include</span>
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach (\App\Services\Toolkit\HardeningScript::SECTIONS as $key => $label)
                                <label class="flex items-center gap-2 text-[12px]">
                                    <input type="checkbox" wire:model="hardeningSections" value="{{ $key }}"
                                        class="rounded border-neutral-300 dark:border-white/20">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <x-forms.button type="button" wire:click="generateHardening">Generate script</x-forms.button>
                </div>

                <div class="mt-4 rounded-lg border border-warning/40 bg-warning/10 p-3 text-[12px]">
                    Dashy never runs this script. Read it first: it changes firewall rules, installs packages and
                    rewrites sysctl values on the target server.
                </div>

                @if ($hardeningScript)
                    <div class="mt-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[11px] text-neutral-500 dark:text-fg-faint">dashy-hardening.sh</span>
                            <button type="button" class="button h-8! px-2.5! text-[12px]!"
                                x-on:click="navigator.clipboard.writeText($refs.hardening.textContent)">Copy</button>
                        </div>
                        <pre x-ref="hardening" class="mt-2 max-h-[32rem] overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $hardeningScript }}</pre>
                    </div>
                @endif
            </x-application.settings-section>
        @endif

        @if ($tab === 'security')
            <x-application.settings-section title="Security toolkit">
                <p class="text-[13px] text-neutral-500 dark:text-fg-dim">
                    Pick the tools, fill in the values, then read the generated script. Dashy never runs it:
                    every command stays visible before it touches a server.
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (\App\Services\Toolkit\SecurityToolkit::TOOLS as $key => $tool)
                        <label class="flex gap-3 rounded-lg border border-neutral-200 p-3 text-[12px] dark:border-white/[0.08]">
                            <input type="checkbox" wire:model="securityTools" value="{{ $key }}"
                                class="mt-0.5 rounded border-neutral-300 dark:border-white/20">
                            <span class="min-w-0">
                                <span class="font-semibold">{{ $tool['label'] }}</span>
                                <span class="ml-1 text-[10px] uppercase tracking-wide text-neutral-500 dark:text-fg-faint">{{ $tool['category'] }}</span>
                                <span class="mt-1 block text-neutral-500 dark:text-fg-dim">{{ $tool['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <x-forms.input id="hashfile" label="Hash file" placeholder="hashes.txt" />
                    <x-forms.input id="hashmode" label="hashcat mode" placeholder="0" helper="0 MD5, 1000 NTLM, 1800 sha512crypt." />
                    <x-forms.input id="wordlist" label="Wordlist" placeholder="/usr/share/wordlists/rockyou.txt" />
                    <x-forms.input id="userlist" label="User list" placeholder="users.txt" />
                    <x-forms.input id="target" label="Target host" placeholder="192.0.2.10" />
                    <x-forms.input id="scanPath" label="Path to scan" placeholder="/var/www" />
                </div>

                <div class="mt-4 grid items-end gap-4 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <x-forms.input id="hashInput" label="Hash to identify" placeholder="$2b$12$..." />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-forms.button type="button" wire:click="identifyHash">Identify hash</x-forms.button>
                        <x-forms.button type="button" wire:click="generateSecurity">Generate script</x-forms.button>
                    </div>
                </div>

                @if (! empty($hashGuesses))
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($hashGuesses as $guess)
                            <div class="rounded-lg border border-neutral-200 p-3 text-[12px] dark:border-white/[0.08]">
                                <p class="font-semibold">{{ $guess['format'] }}</p>
                                <p class="mt-1 text-neutral-500 dark:text-fg-dim">{{ $guess['detail'] }}</p>
                                <p class="mt-2 font-mono text-[11px]">
                                    john: {{ $guess['john'] ?? 'n/a' }} · hashcat: {{ $guess['hashcat'] ?? 'n/a' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($securityScript)
                    <div class="mt-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[11px] text-neutral-500 dark:text-fg-faint">dashy-security.sh</span>
                            <button type="button" class="button h-8! px-2.5! text-[12px]!"
                                x-on:click="navigator.clipboard.writeText($refs.security.textContent)">Copy</button>
                        </div>
                        <pre x-ref="security" class="mt-2 max-h-[32rem] overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $securityScript }}</pre>
                    </div>
                @endif
            </x-application.settings-section>
        @endif

        @if ($tab === 'mods')
            <x-application.settings-section title="Minecraft mods, plugins and datapacks">
                <p class="text-[13px] text-neutral-500 dark:text-fg-dim">
                    Search the public Modrinth catalogue, then generate the download commands for the
                    mods/ or plugins/ folder of a Minecraft server. No API key needed.
                </p>

                <div class="mt-4 grid items-end gap-4 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <x-forms.input id="modQuery" label="Search" placeholder="performance, lithium, worldedit" />
                    </div>
                    <div>
                        <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Loader</label>
                        <select wire:model="modLoader" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                            @foreach (\App\Services\Toolkit\ModrinthClient::LOADERS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-forms.input id="modVersion" label="Game version" placeholder="1.21.4" />
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-forms.button type="button" wire:click="searchMods">Search Modrinth</x-forms.button>
                    <x-forms.button type="button" wire:click="planMods">Build install commands</x-forms.button>
                </div>

                @if ($modError)
                    <p class="mt-4 text-[12px] text-warning">{{ $modError }}</p>
                @endif

                @if (! empty($modResults))
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($modResults as $project)
                            <label class="flex gap-3 rounded-lg border border-neutral-200 p-3 text-[12px] dark:border-white/[0.08]">
                                <input type="checkbox" wire:model="modSelection" value="{{ $project['id'] }}"
                                    class="mt-1 rounded border-neutral-300 dark:border-white/20">
                                <span class="min-w-0">
                                    <span class="font-semibold">{{ $project['title'] }}</span>
                                    <span class="mt-0.5 block text-neutral-500 dark:text-fg-dim">{{ \Illuminate\Support\Str::limit($project['description'], 120) }}</span>
                                    <span class="mt-1 block text-[11px] text-neutral-500 dark:text-fg-faint">
                                        {{ number_format($project['downloads']) }} downloads
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif

                @if (! empty($modPlan))
                    <div class="mt-4">
                        <span class="text-[11px] text-neutral-500 dark:text-fg-faint">install-mods.sh</span>
                        <pre class="mt-2 max-h-[28rem] overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">#!/usr/bin/env bash
# Run inside the Minecraft server directory (the folder that contains mods/ or plugins/).
set -euo pipefail
@foreach ($modPlan as $item)
{{ $item['command'] ?? '# '.$item['title'].': no matching file for this loader and version' }}
@endforeach</pre>
                    </div>
                @endif
            </x-application.settings-section>
        @endif

        @if ($tab === 'vm')
            <x-application.settings-section title="DashyVMToolkit">
                <p class="text-[13px] text-neutral-500 dark:text-fg-dim">
                    The guest-side setup a virtual machine needs to behave like a real host: guest agent,
                    VirtIO scheduler, discards, serial console. Run it once inside the VM after the first boot.
                </p>

                <div class="mt-4 grid gap-4 lg:grid-cols-4">
                    <div>
                        <label class="text-[12px] text-neutral-500 dark:text-fg-faint">Distribution family</label>
                        <select wire:model="vmFamily" class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                            @foreach (\App\Services\Toolkit\VmToolkitScript::FAMILIES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lg:col-span-3">
                        <span class="text-[12px] text-neutral-500 dark:text-fg-faint">Include</span>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach (\App\Services\Toolkit\VmToolkitScript::OPTIONS as $key => $label)
                                <label class="flex items-start gap-2 text-[12px]">
                                    <input type="checkbox" wire:model="vmOptions" value="{{ $key }}" class="mt-0.5 rounded border-neutral-300 dark:border-white/20">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <x-forms.button type="button" wire:click="generateVmToolkit">Generate script</x-forms.button>
                </div>

                @if ($vmScript)
                    <div class="mt-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[11px] text-neutral-500 dark:text-fg-faint">dashy-vm-toolkit.sh</span>
                            <button type="button" class="button h-8! px-2.5! text-[12px]!"
                                x-on:click="navigator.clipboard.writeText($refs.vmtoolkit.textContent)">Copy</button>
                        </div>
                        <pre x-ref="vmtoolkit" class="mt-2 max-h-[32rem] overflow-auto rounded-lg border border-neutral-200 p-3 text-[12px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $vmScript }}</pre>
                    </div>
                @endif
            </x-application.settings-section>
        @endif
    </div>
</div>
