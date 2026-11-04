<?php

namespace App\Http\Controllers\Api;

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use App\Services\Cli\CliPairing;
use App\Services\UpdateChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The two endpoints the `dashy` CLI needs: exchange a pairing code for a token,
 * then read the instance over that token.
 *
 * Pairing is intentionally outside the versioned API group: it runs before the
 * terminal holds a token, and it is rate limited by the route.
 */
class CliController
{
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:64'],
        ]);

        $payload = CliPairing::redeem($data['code']);

        if ($payload === null) {
            return response()->json([
                'message' => 'That pairing code is invalid, already used, or expired. Generate a new one in Settings → CLI.',
            ], 422);
        }

        $user = User::find($payload['user_id']);

        if ($user === null) {
            return response()->json(['message' => 'The user behind this code no longer exists.'], 422);
        }

        $team = $payload['team_id'] !== null ? Team::find($payload['team_id']) : $user->currentTeam();
        $name = 'Dashy CLI · '.($data['name'] ?: 'terminal');

        $token = $user->createToken(
            $name,
            $payload['abilities'],
            now()->addDays(30),
            $team?->id,
        );

        auditLog('api.cli.paired', [
            'team_id' => $team?->id,
            'resource' => 'cli',
            'abilities' => $payload['abilities'],
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => $payload['abilities'],
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'team' => $team !== null ? ['id' => $team->id, 'name' => $team->name] : null,
            'instance' => $this->instancePayload(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $teamId = $token?->team_id ?? $user?->currentTeam()?->id;

        $servers = Server::query()
            ->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->limit(50)
            ->get(['uuid', 'name', 'ip', 'is_reachable'])
            ->map(fn (Server $server) => [
                'uuid' => $server->uuid,
                'name' => $server->name,
                'ip' => $server->ip,
                'reachable' => (bool) $server->is_reachable,
            ])
            ->values();

        $check = UpdateChecker::run();

        return response()->json([
            'instance' => $this->instancePayload(),
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
            ],
            'team' => $teamId !== null ? Team::find($teamId)?->only(['id', 'name']) : null,
            'servers' => $servers,
            'update' => [
                'available' => $check['available'],
                'current' => $check['current'],
                'latest' => $check['latest'],
                'checked_at' => $check['checked_at'],
                'error' => $check['error'],
            ],
            'abilities' => $token?->abilities ?? ['read'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function instancePayload(): array
    {
        return [
            'name' => (string) (instanceSettings()->instance_name ?: 'Dashy'),
            'url' => (string) (instanceSettings()->fqdn ?: url('/')),
            'version' => (string) config('constants.dashy.version'),
            'timezone' => (string) config('app.timezone'),
        ];
    }
}
