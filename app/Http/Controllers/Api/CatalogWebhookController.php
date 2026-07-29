<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCatalogChunk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatalogWebhookController extends Controller
{
    /**
     * Receive catalog webhooks from Crema S1 and queue in 50-item chunks.
     */
    public function handle(Request $request): JsonResponse
    {
        // Signature verification is handled upstream by the internal.webhook
        // middleware (HMAC-SHA256 of the raw body via X-Crema-Signature +
        // WEBHOOK_SECRET). The header is mandatory there — a missing or
        // invalid signature never reaches this controller.

        $products = $request->input('products');

        if (! is_array($products)) {
            // Support payload if single product object or list of products directly
            $payload = $request->all();
            $products = isset($payload[0]) ? $payload : [$payload];
        }

        if (empty($products)) {
            return response()->json(['message' => 'No products in payload'], 400);
        }

        $chunks = array_chunk($products, 50);
        $dispatchedChunks = 0;

        foreach ($chunks as $chunk) {
            ProcessCatalogChunk::dispatchSync($chunk);
            $dispatchedChunks++;
        }

        Log::info('CatalogWebhookController: Queued '.count($products)." products in {$dispatchedChunks} Redis job chunks.");

        return response()->json([
            'status' => 'queued',
            'total_products' => count($products),
            'chunks_queued' => $dispatchedChunks,
        ], 202);
    }
}
