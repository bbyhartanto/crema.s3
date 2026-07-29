<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CustomerSyncWebhookController
 *
 * Fires after S3 customer register/login to push a lightweight profile
 * projection to S1. S1 stores id, name, email, whatsapp_number only.
 *
 * Writes directly to S1's database to bypass Cloudflare proxy.
 */
class CustomerSyncWebhookController extends Controller
{
    /**
     * Push customer profile to S1.
     * Called internally by S3 after register/login.
     */
    public static function pushToS1(Customer $customer): void
    {
        try {
            // Match by email (legacy S1 customers may have different UUIDs)
            $existing = DB::connection('s1_sync')->table('customers')
                ->where('email', strtolower(trim($customer->email)))
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                DB::connection('s1_sync')->table('customers')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $customer->name,
                        'whatsapp_number' => $customer->whatsapp_number,
                        'phone_number' => $customer->phone_number,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::connection('s1_sync')->table('customers')->insert([
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => strtolower(trim($customer->email)),
                    'whatsapp_number' => $customer->whatsapp_number,
                    'phone_number' => $customer->phone_number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Customer sync to S1 failed for {$customer->email}: " . $e->getMessage());
        }
    }
}
