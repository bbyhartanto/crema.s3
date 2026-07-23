<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class JwksController extends Controller
{
    /**
     * Expose the JSON Web Key Set (JWKS) for JWT verification.
     */
    public function index(): JsonResponse
    {
        $publicKeyPath = storage_path('oauth-public.key');

        if (!file_exists($publicKeyPath)) {
            return response()->json(['error' => 'Public key not found'], 500);
        }

        $publicKeyPem = file_get_contents($publicKeyPath);
        $res = openssl_pkey_get_public($publicKeyPem);

        if (!$res) {
            return response()->json(['error' => 'Invalid public key'], 500);
        }

        $details = openssl_pkey_get_details($res);

        if (!isset($details['rsa'])) {
            return response()->json(['error' => 'Key is not RSA'], 500);
        }

        $n = $this->base64UrlEncode($details['rsa']['n']);
        $e = $this->base64UrlEncode($details['rsa']['e']);

        $jwk = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => md5($publicKeyPem),
            'n' => $n,
            'e' => $e,
        ];

        return response()->json([
            'keys' => [$jwk],
        ])->header('Cache-Control', 'max-age=86400, public');
    }

    /**
     * Base64URL encode binary string without padding.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
