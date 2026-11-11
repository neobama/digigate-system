<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agent API (Claude Code / Virtual Employee / MCP)
    |--------------------------------------------------------------------------
    |
    | Read-only API for external agents. Authenticate with:
    |   Authorization: Bearer <token>
    | or header:
    |   X-Digigate-Agent-Token: <token>
    |
    */

    'enabled' => (bool) env('AGENT_API_ENABLED', true),

    'token' => env('AGENT_API_TOKEN'),

    'rate_limit_per_minute' => (int) env('AGENT_API_RATE_LIMIT', 120),

];
