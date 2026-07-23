<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenBlacklist
{
    /**
     * Handle an incoming request and check if token is blacklisted in Redis.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token) {
            $jti = $this->extractJti($token);

            if ($jti && Redis::exists("jwt:blacklist:{$jti}")) {
                return response()->json([
                    'message' => 'Token has been revoked',
                    'error' => 'token_blacklisted',
                ], 401);
            }
        }

        return $next($request);
    }

    /**
     * Extract JTI (JWT ID) from bearer token payload safely.
     */
    private function extractJti(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
        if (!$payloadJson) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        return $payload['jti'] ?? null;
    }
}
