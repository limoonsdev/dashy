<?php

use App\Mcp\Servers\DashyServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', DashyServer::class)
    ->middleware(['mcp.enabled', 'api.ip.allowed', 'auth:sanctum', 'api.token.team', 'mcp.team.enabled']);
