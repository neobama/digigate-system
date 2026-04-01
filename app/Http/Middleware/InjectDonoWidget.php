<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectDonoWidget
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            !$request->isMethod('GET') ||
            $request->expectsJson() ||
            !$response->headers->has('Content-Type') ||
            !str_contains((string) $response->headers->get('Content-Type'), 'text/html')
        ) {
            return $response;
        }

        $content = $response->getContent();
        if (!is_string($content) || !str_contains($content, '</body>')) {
            return $response;
        }

        $widget = view('filament.components.dono-chat-widget')->render();
        $response->setContent(str_replace('</body>', $widget . '</body>', $content));

        return $response;
    }
}

