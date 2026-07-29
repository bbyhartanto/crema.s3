<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new customer and issue Crema Passport JWT token.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:customers,email',
            'password' => 'required|string|min:8',
            'whatsapp_number' => 'required|string|max:30',
            'phone_number' => 'nullable|string|max:30',
        ]);

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'whatsapp_number' => $validated['whatsapp_number'],
            'phone_number' => $validated['phone_number'] ?? null,
            'is_active' => true,
            'last_login_at' => now(),
        ]);

        $tokenResult = $customer->createToken('Crema Passport Token');
        $accessToken = $tokenResult->accessToken;

        // S3 → S1 read-only projection is dispatched automatically by
        // CustomerObserver on the "saved" Eloquent event (see AppServiceProvider).

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'token' => $accessToken,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'whatsapp_number' => $customer->whatsapp_number,
                'phone_number' => $customer->phone_number,
            ],
        ], 201);
    }

    /**
     * Authenticate customer and issue Passport JWT token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $customer = Customer::where('email', $credentials['email'])->first();

        if (! $customer) {
            return response()->json(['message' => 'No account found with this email address.'], 401);
        }

        if (! Hash::check($credentials['password'], $customer->password)) {
            return response()->json(['message' => 'Incorrect password. Please try again.'], 401);
        }

        $tokenResult = $customer->createToken('Crema Passport Token');
        $accessToken = $tokenResult->accessToken;

        // S3 → S1 read-only projection is dispatched automatically by
        // CustomerObserver on the "saved" Eloquent event (see AppServiceProvider).

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ],
        ]);
    }

    /**
     * Logout customer and blacklist JWT token JTI in Redis.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && method_exists($user, 'token') && $user->token()) {
            $user->token()->revoke();
        }

        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            $parts = explode('.', $bearerToken);
            if (count($parts) === 3) {
                $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
                $payload = json_decode($payloadJson, true);

                $jti = $payload['jti'] ?? null;
                $exp = $payload['exp'] ?? null;

                if ($jti) {
                    $ttl = $exp ? max(1, $exp - time()) : 86400; // Default 24h if no exp
                    Redis::setex("jwt:blacklist:{$jti}", $ttl, 'revoked');
                }
            }
        }

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Get authenticated customer profile.
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'customer' => $request->user(),
        ]);
    }

    /**
     * Issue a central Crema Passport password reset token and recovery link.
     * The reset URL points to the storefront the request came from.
     * The email is sent via S1 since S1 owns the store context + notification setup.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'store_slug' => 'nullable|string',
            'store_domain' => 'nullable|string',
        ]);

        $email = strtolower(trim($request->email));
        $customer = Customer::where('email', $email)->first();

        if ($customer && $customer->is_active) {
            $token = Password::broker('customers')->createToken($customer);

            // Build the reset URL pointing to the correct storefront
            $baseUrl = env('FRONTEND_URL', 'http://localhost:3000');
            $storeSlug = $request->input('store_slug');
            $storeDomain = $request->input('store_domain');

            // If store context is provided, build tenant-scoped URL
            if ($storeSlug) {
                $baseUrl .= '/'.$storeSlug;
            } elseif ($storeDomain) {
                $baseUrl = 'https://'.preg_replace('/^https?:\/\//', '', $storeDomain);
            }

            $resetUrl = $baseUrl.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $customer->email,
            ]);

            // Delegate email sending to S1 (it owns store context + mail config)
            $s1BaseUrl = env('S1_API_BASE_URL', 'http://127.0.0.1:8000');
            try {
                Http::timeout(5)->post(rtrim($s1BaseUrl, '/').'/api/customer/send-reset-email', [
                    'email' => $customer->email,
                    'reset_url' => $resetUrl,
                    'store_slug' => $storeSlug,
                    'store_domain' => $storeDomain,
                ]);
            } catch (\Throwable $e) {
                Log::warning('S3 forgotPassword: failed to delegate email to S1: '.$e->getMessage());
            }

            return response()->json([
                'message' => 'If an account exists for that email, a central Crema Passport recovery link has been sent.',
                'reset_url' => $resetUrl,
            ]);
        }

        return response()->json([
            'message' => 'If an account exists for that email, a central Crema Passport recovery link has been sent.',
        ]);
    }

    /**
     * Reset Crema Passport password using token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $customer, string $password) {
                $customer->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                // Revoke active Passport tokens
                $customer->tokens()->each(function ($token) {
                    $token->revoke();
                });
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Your Crema Passport password has been successfully reset. You can now sign in with your new password.',
        ]);
    }
}
