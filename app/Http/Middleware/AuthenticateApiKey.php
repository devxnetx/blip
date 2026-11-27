<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $match)) {
            return $this->denied();
        }

        $key = ApiKey::findActive($match[1]);

        if ($key === null) {
            return $this->denied();
        }

        $key->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('apiKey', $key);

        return $next($request);
    }

    private function denied(): Response
    {
        return response()->json([
            'message' => 'Bearer token required.',
        ], 401);
    }
}
