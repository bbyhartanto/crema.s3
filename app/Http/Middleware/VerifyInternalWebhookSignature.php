<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Verifies inbound internal webhooks from the sibling Crema services (S1/S3).
 *
 * Contract (shared with the customer-sync sender on S3 and the catalog sender
 * on S1):
 *   Header  X-Crema-Signature  =  hash_hmac('sha256', <raw request body>, WEBHOOK_SECRET)
 *
 * The signature header is MANDATORY: a request with no header is rejected
 * with 401. This fixes the missing-header bypass that previously let unsigned
 * requests through whenever X-Crema-Signature was absent.
 *
 * If the shared secret is not configured (WEBHOOK_SECRET unset), the endpoint
 * fails closed in non-local environments (500). In local/testing it is allowed
 * through so dev + tests work without provisioning a secret.
 */
class VerifyInternalWebhookSignature
{
    public function handle(Request $request, Closure $next): mixed
    {
        $secret = (string) config('services.crema.webhook_secret', '');

        if ($secret === '') {
            if (app()->environment('local', 'testing')) {
                return $next($request);
            }

            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        $signature = $request->header('X-Crema-Signature');

        if (empty($signature)) {
            return response()->json(['error' => 'Missing signature'], 401);
        }

        $computed = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($computed, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
