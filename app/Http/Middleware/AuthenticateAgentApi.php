<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgentApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('agent_api.enabled')) {
            return response()->json([
                'error' => 'agent_api_disabled',
                'message' => 'Agent API is disabled.',
            ], 503);
        }

        $expected = (string) config('agent_api.token');
        if ($expected === '') {
            return response()->json([
                'error' => 'agent_api_not_configured',
                'message' => 'AGENT_API_TOKEN is not set on the server.',
            ], 503);
        }

        $provided = $this->extractToken($request);
        if ($provided === null || ! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Invalid or missing agent API token.',
            ], 401);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('X-Digigate-Agent-Token');
        if (is_string($header) && $header !== '') {
            return trim($header);
        }

        $auth = $request->header('Authorization');
        if (is_string($auth) && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m)) {
            return $m[1];
        }

        return null;
    }
}
