<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\BuildsResponse;
use App\Mcp\Concerns\ResolvesTeam;
use App\Services\Toolkit\DomainLookup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class LookupDomain extends Tool
{
    protected string $name = 'lookup_domain';

    protected string $description = 'Check whether a domain is registered (RDAP) and return its registrar, key dates and nameservers.';

    use BuildsResponse;
    use ResolvesTeam;

    public function handle(Request $request): Response
    {
        if ($error = $this->ensureAbility($request, 'read', $this->name)) {
            return $error;
        }

        $teamId = $this->resolveTeamId($request);

        if (is_null($teamId)) {
            return $this->mcpError($request, 'Invalid token.');
        }

        $domain = $request->get('domain');

        if (! is_string($domain) || $domain === '') {
            return $this->mcpError($request, 'domain argument is required.');
        }

        $result = app(DomainLookup::class)->rdap($domain);

        if (($result['status'] ?? null) === 'invalid') {
            return $this->mcpError($request, (string) ($result['error'] ?? 'Invalid domain.'));
        }

        return $this->mcpSuccess($request, $this->respond($this->scrubSensitive($result)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->required()->description('The domain to check, such as example.com.'),
        ];
    }
}
