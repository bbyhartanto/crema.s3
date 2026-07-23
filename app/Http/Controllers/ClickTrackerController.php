<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ClickTrackerController extends Controller
{
    /**
     * Fast Redis click tracker for marketplace outbound links.
     * Logs click data into Redis list instantly and returns HTTP 302 redirect.
     */
    public function track(Request $request): RedirectResponse
    {
        $productId = $request->query('product_id', 'unknown');
        $target = strtolower((string) $request->query('target', 'marketplace'));
        $tenantId = $request->query('tenant_id');
        $destinationUrl = $request->query('destination_url');

        // Fast drop into Redis buffer (O(1) execution time)
        $clickData = [
            'product_id' => $productId,
            'target' => $target,
            'tenant_id' => $tenantId,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'clicked_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        Redis::rpush('clicks:buffer', json_encode($clickData));

        // Default URL fallback if custom destination URL not provided
        if (!$destinationUrl) {
            $destinationUrl = "https://crema.supply";
        }

        return redirect()->away($destinationUrl, 302);
    }
}
