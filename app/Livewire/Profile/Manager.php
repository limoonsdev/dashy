<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * The account control room: identity, active browser sessions, tokens handed to
 * the CLI and the shortcuts to the pages that change them.
 */
class Manager extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $sessions = [];

    /** @var array<int, array<string, mixed>> */
    public array $tokens = [];

    public function mount(): void
    {
        $this->load();
    }

    public function revokeToken(int $id): void
    {
        $token = auth()->user()->tokens()->whereKey($id)->first();

        if ($token === null) {
            return;
        }

        $token->delete();

        auditLog('ui.profile.token_revoked', [
            'team_id' => currentTeam()?->id,
            'resource' => 'profile',
            'token_id' => $id,
        ]);

        $this->load();
        $this->dispatch('success', 'That token no longer works.');
    }

    public function revokeOtherSessions(): void
    {
        $deleted = DB::table('sessions')
            ->where('user_id', auth()->id())
            ->where('id', '!=', session()->getId())
            ->delete();

        auditLog('ui.profile.sessions_revoked', [
            'team_id' => currentTeam()?->id,
            'resource' => 'profile',
            'count' => $deleted,
        ]);

        $this->load();
        $this->dispatch('success', $deleted === 0 ? 'No other session was open.' : 'Signed out '.$deleted.' other session(s).');
    }

    public function render()
    {
        return view('livewire.profile.manager');
    }

    private function load(): void
    {
        $current = session()->getId();

        $this->sessions = DB::table('sessions')
            ->where('user_id', auth()->id())
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'current' => hash_equals((string) $current, (string) $session->id),
                'ip' => $session->ip_address,
                'agent' => $this->describeAgent((string) $session->user_agent),
                'last_activity' => now()->setTimestamp((int) $session->last_activity)->diffForHumans(),
            ])
            ->all();

        $this->tokens = auth()->user()->tokens()
            ->latest()
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => implode(', ', (array) $token->abilities),
                'last_used_at' => $token->last_used_at?->diffForHumans() ?? 'never',
                'expires_at' => $token->expires_at?->toFormattedDateString() ?? 'no expiry',
                'is_cli' => str_starts_with((string) $token->name, 'Dashy CLI'),
            ])
            ->all();
    }

    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            str_contains($agent, 'curl/') => 'curl',
            default => 'Unknown client',
        };

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown OS',
        };

        return $browser.' on '.$platform;
    }
}
