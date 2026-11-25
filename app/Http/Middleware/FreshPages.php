<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FreshPages
{
    /**
     * HTML stays uncached while the site is still changing.
     * Static files are cached by the web server instead.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Cache-Control')) {
            $response->headers->set('Cache-Control', 'private, no-cache');
        }

        return $response;
    }
}
