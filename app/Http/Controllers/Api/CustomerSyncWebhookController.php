<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CustomerSyncWebhookController
 *
 * Fires after S3 customer register/login to push a lightweight profile
 * projection to S1. S1 stores id, name, email, whatsapp_number only.
 *
 * This is a fire-and-forget best-effort sync. Failures are logged but
 * do not block the auth flow.
 */
class CustomerSyncWebhookController extends Controller
{
    /**
     * Push customer profile to S1.
     * Called internally by S3 after register/login.
     */
    public static function pushToS1(Customer $customer): void
    {
        $s1BaseUrl = env('S1_API_BASE_URL', 'http://127.0.0.1:8000');
        $url = rtrim($s1BaseUrl, '/') . '/api/customer/sync/customer';

        $payload = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'whatsapp_number' => $customer->whatsapp_number,
            'phone_number' => $customer->phone_number,
        ];

        try {
            $response = Http::timeout(5)->post($url, $payload);

            if (!$response->successful()) {
                Log::warning("Customer sync to S1 failed for {$customer->email}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning("Customer sync to S1 failed for {$customer->email}: " . $e->getMessage());
        }
    }
}
