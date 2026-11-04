<div>
    <x-slot:title>CLI | Dashy</x-slot:title>

    <x-settings.layout>
        <div class="application-settings-form flex w-full min-w-0 flex-col gap-6">
            <x-application.settings-section title="Dashy CLI"
                helper="Control this instance from a terminal on Linux, macOS or Windows.">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="text-[12px] font-medium">1. Install the CLI</p>
                        <pre class="mt-1 overflow-auto rounded-lg border border-neutral-200 p-2 text-[11px] leading-5 dark:border-white/[0.08] dark:bg-white/[0.02]">{{ $install }}</pre>
                        <p class="mt-1 text-[11px] text-neutral-500 dark:text-fg-dim">
                            Windows (PowerShell):
                            <code>irm https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallCLI.ps1 | iex</code>
                        </p>
                    </div>
                    <div>
                        <p class="text-[12px] font-medium">2. Pick what the terminal may do</p>
                        <select wire:model="ability"
                            class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                            <option value="read">Read only — status, servers, logs</option>
                            <option value="write">Manage — deploy and change resources</option>
                            <option value="deploy">Full operator — deploy, delete and act</option>
                        </select>
                        <p class="mt-2 text-[12px] font-medium">Keep this terminal signed in for</p>
                        <select wire:model="access"
                            class="mt-1 w-full rounded-lg border border-neutral-200 p-2 text-[13px] dark:border-white/[0.08] dark:bg-white/[0.02]">
                            <option value="14">14 days — recommended</option>
                            <option value="30">30 days</option>
                            <option value="90">90 days</option>
                            <option value="never">Until I revoke it (no expiry)</option>
                        </select>
                        <p class="mt-1 text-[11px] text-neutral-500 dark:text-fg-dim">
                            The code is single use, bound to your account and this team, and expires in
                            {{ \App\Services\Cli\CliPairing::MINUTES }} minutes.
                        </p>
                        <x-forms.button type="button" wire:click="generate" class="mt-2">
                            <x-reicon name="keys" class="size-3.5" />
                            Generate a sign-in code
                        </x-forms.button>
                    </div>
                </div>

                @if ($code)
                    <div class="mt-4 rounded-lg border border-dashy/40 bg-dashy/[0.05] p-4"
                        x-data="{ copied: false }">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-fg-faint">
                            Run this in your terminal
                        </p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <code class="rounded-md bg-white px-2 py-1 font-mono text-[15px] tracking-wider dark:bg-black/40">dashy connect {{ $code }}</code>
                            <button type="button" class="button h-7! px-2! text-[11px]!"
                                x-on:click="navigator.clipboard.writeText('dashy connect {{ $code }}'); copied = true; setTimeout(() => copied = false, 1500)">
                                <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                            </button>
                            <button type="button" class="button h-7! px-2! text-[11px]!" wire:click="cancel">Cancel code</button>
                        </div>
                        <p class="mt-2 text-[11px] text-neutral-500 dark:text-fg-dim">
                            Expires {{ \Illuminate\Support\Carbon::parse($expiresAt)->diffForHumans() }}. The terminal
                            receives a token with the duration you chose and never asks for your password.
                        </p>
                    </div>
                @endif
            </x-application.settings-section>

            <x-application.settings-section title="Paired terminals"
                helper="Tokens this page created. Revoking one signs that terminal out immediately.">
                @if ($tokens === [])
                    <p class="text-[12px] text-neutral-500 dark:text-fg-dim">No terminal paired yet.</p>
                @else
                    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-white/[0.08]">
                        <table class="w-full text-left text-[12px]">
                            <thead class="bg-neutral-50 text-[11px] text-neutral-500 dark:bg-white/[0.03] dark:text-fg-faint">
                                <tr>
                                    <th class="px-3 py-2 font-medium">Token</th>
                                    <th class="px-3 py-2 font-medium">Abilities</th>
                                    <th class="px-3 py-2 font-medium">Last used</th>
                                    <th class="px-3 py-2 font-medium">Expires</th>
                                    <th class="px-3 py-2 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tokens as $token)
                                    <tr class="border-t border-neutral-100 dark:border-white/[0.05]">
                                        <td class="px-3 py-2">{{ $token['name'] }}</td>
                                        <td class="px-3 py-2 font-mono text-[11px]">{{ $token['abilities'] }}</td>
                                        <td class="px-3 py-2">{{ $token['last_used_at'] ?? 'never' }}</td>
                                        <td class="px-3 py-2">{{ $token['expires_at'] ?? 'no expiry' }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" class="button h-7! px-2! text-[11px]! text-error"
                                                wire:click="revoke({{ $token['id'] }})" wire:confirm="Revoke this CLI token?">
                                                Revoke
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-application.settings-section>

            <x-application.settings-section title="What the CLI can do"
                helper="Everything below goes through the same permissions as the web UI.">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['status', 'Instance health, version and update state'],
                        ['servers', 'Servers, reachability and resources'],
                        ['deploy', 'Deploy an application by name or uuid'],
                        ['logs', 'Tail the log of a resource'],
                        ['update', 'Check for updates and install one'],
                        ['tui', 'Full-screen console (dashy with no arguments)'],
                    ] as [$command, $description])
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-white/[0.08]">
                            <p class="font-mono text-[12px] font-semibold">dashy {{ $command }}</p>
                            <p class="mt-0.5 text-[11px] text-neutral-500 dark:text-fg-dim">{{ $description }}</p>
                        </div>
                    @endforeach
                </div>
            </x-application.settings-section>
        </div>
    </x-settings.layout>
</div>
