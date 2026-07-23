<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

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
            'id' => (string) \Illuminate\Support\Str::uuid(),
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

        if (!$customer || !Hash::check($credentials['password'], $customer->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $tokenResult = $customer->createToken('Crema Passport Token');
        $accessToken = $tokenResult->accessToken;

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
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->email));
        $customer = Customer::where('email', $email)->first();

        if ($customer && $customer->is_active) {
            $token = \Illuminate\Support\Facades\Password::broker('customers')->createToken($customer);

            $platformBaseUrl = env('FRONTEND_URL', 'http://localhost:3000');
            $resetUrl = $platformBaseUrl . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $customer->email,
            ]);

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

        $status = \Illuminate\Support\Facades\Password::broker('customers')->reset(
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

        if ($status !== \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Your Crema Passport password has been successfully reset. You can now sign in with your new password.',
        ]);
    }
}
