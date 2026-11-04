<?php

namespace App\Livewire\Settings;

use App\Services\Cli\CliBinaryBuilder;
use App\Services\Cli\CliPairing;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Issues the one-time code the `dashy` CLI exchanges for an API token, and lists
 * the tokens that already paired so they can be revoked.
 */
class Cli extends Component
{
    public ?string $code = null;

    public ?string $expiresAt = null;

    public string $ability = 'deploy';

    /** "14", "30", "90" or "never". Infinite access is a deliberate choice. */
    public string $access = '14';

    /** "binary" downloads an executable; "command" shows the install one-liner. */
    public string $delivery = 'binary';

    public string $platform = 'linux-x64';

    public ?string $downloadUrl = null;

    public ?string $downloadExpiresAt = null;

    public ?string $buildError = null;

    /** @var array<int, array<string, mixed>> */
    public array $tokens = [];

    public function mount(): void
    {
        if (! isInstanceAdmin() && ! auth()->user()?->isAdminOfTeam(currentTeam()->id)) {
            abort(403);
        }

        $this->loadTokens();
    }

    public function generate(): void
    {
        if (! (instanceSettings()->is_cli_pairing_enabled ?? true)) {
            $this->dispatch('error', 'CLI pairing is disabled for this instance. Enable it in Settings → Experience.');

            return;
        }

        $abilities = CliPairing::ABILITIES[$this->ability] ?? ['read'];

        $accessDays = $this->access === 'never' ? null : (int) $this->access;
        $issued = CliPairing::issue(auth()->user(), currentTeam(), $abilities, CliPairing::MINUTES, $accessDays);

        $this->code = $issued['code'];
        $this->expiresAt = $issued['expires_at'];

        auditLog('ui.settings.cli.code_created', [
            'team_id' => currentTeam()->id,
            'resource' => 'cli',
            'abilities' => $issued['abilities'],
        ]);

        $this->dispatch('success', 'Code ready. It works once and expires in '.CliPairing::MINUTES.' minutes.');
    }

    /**
     * Build the requested platform (Bun cross-compiles it) and hand back a URL
     * that is signed for two hours. The binary stays cached for the same window.
     */
    public function prepareDownload(): void
    {
        $this->downloadUrl = $this->downloadExpiresAt = $this->buildError = null;

        if ($this->delivery !== 'binary') {
            return;
        }

        $this->validate([
            'platform' => ['required', 'string', 'in:'.implode(',', array_keys(CliBinaryBuilder::PLATFORMS))],
        ]);

        try {
            app(CliBinaryBuilder::class)->ensure($this->platform);

            $expires = now()->addHours(CliBinaryBuilder::TTL_HOURS);
            $this->downloadUrl = URL::temporarySignedRoute('cli.download', $expires, ['platform' => $this->platform]);
            $this->downloadExpiresAt = $expires->toIso8601String();

            auditLog('ui.settings.cli.binary_built', [
                'team_id' => currentTeam()->id,
                'resource' => 'cli',
                'platform' => $this->platform,
            ]);

            $this->dispatch('success', 'Binary ready. The download link expires in two hours.');
        } catch (\Throwable $e) {
            $this->buildError = $e->getMessage();
        }
    }

    public function cancel(): void
    {
        if ($this->code !== null) {
            CliPairing::forget($this->code);
        }

        $this->code = null;
        $this->expiresAt = null;
    }

    public function revoke(int $id): void
    {
        $token = auth()->user()->tokens()->whereKey($id)->first();

        if ($token === null || ! str_starts_with($token->name, 'Dashy CLI')) {
            return;
        }

        $token->delete();

        auditLog('ui.settings.cli.token_revoked', [
            'team_id' => currentTeam()->id,
            'resource' => 'cli',
            'token_id' => $id,
        ]);

        $this->loadTokens();
        $this->dispatch('success', 'That CLI token no longer works.');
    }

    public function render()
    {
        return view('livewire.settings.cli', [
            'install' => 'curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallCLI.sh | bash',
            'platforms' => CliBinaryBuilder::PLATFORMS,
            'ttlHours' => CliBinaryBuilder::TTL_HOURS,
        ]);
    }

    private function loadTokens(): void
    {
        $this->tokens = auth()->user()->tokens()
            ->where('name', 'like', 'Dashy CLI%')
            ->latest()
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => implode(', ', (array) $token->abilities),
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'expires_at' => $token->expires_at?->toFormattedDateString(),
            ])
            ->all();
    }
}
