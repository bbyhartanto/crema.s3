<?php

namespace App\Jobs;

use App\Models\CatalogItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessCatalogChunk implements ShouldQueue
{
    use Queueable;

    /**
     * The chunk of product items (max 50 items).
     *
     * @var array
     */
    public array $products;

    /**
     * Create a new job instance.
     */
    public function __construct(array $products)
    {
        $this->products = $products;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $count = count($this->products);
        Log::info("ProcessCatalogChunk: Processing batch of {$count} products for S3 Catalog and Meilisearch.");

        $meiliDocuments = [];

        foreach ($this->products as $product) {
            $productId = $product['id'] ?? null;
            if (!$productId) {
                continue;
            }

            $flavorNotes = $product['flavor_notes'] ?? [];
            if (is_string($flavorNotes)) {
                $flavorNotes = array_filter(array_map('trim', explode(',', $flavorNotes)));
            }

            $itemData = [
                'id' => (string) $productId,
                'store_id' => $product['store_id'] ?? null,
                'store_name' => $product['store_name'] ?? 'Crema Roastery',
                'store_slug' => $product['store_slug'] ?? 'default-roaster',
                'name' => $product['name'] ?? 'Coffee Beans',
                'slug' => $product['slug'] ?? '',
                'description' => $product['description'] ?? '',
                'origin' => $product['origin'] ?? 'Indonesia',
                'roast_level' => $product['roast_level'] ?? 'Medium',
                'process' => $product['process'] ?? 'Full Wash',
                'flavor_notes' => array_values((array) $flavorNotes),
                'field_values' => $product['field_values'] ?? null,
                'marketplace_links' => $product['marketplace_links'] ?? null,
                'price_min' => (float) ($product['price_min'] ?? $product['price'] ?? 0),
                'price_max' => (float) ($product['price_max'] ?? $product['price'] ?? 0),
                'image_url' => $product['image_url'] ?? $product['image'] ?? null,
                'is_available' => (bool) ($product['is_available'] ?? true),
            ];

            // Upsert in MySQL database
            CatalogItem::updateOrCreate(
                ['id' => $itemData['id']],
                $itemData
            );

            $meiliDocuments[] = $itemData;
        }

        // Sync to Meilisearch container if configured
        $meiliHost = env('MEILISEARCH_HOST', 'http://127.0.0.1:7700');
        $meiliKey = env('MEILISEARCH_KEY', 'masterKey');

        if (!empty($meiliDocuments)) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $meiliKey,
                    'Content-Type' => 'application/json',
                ])->post("{$meiliHost}/indexes/products/documents", $meiliDocuments);

                if ($response->successful()) {
                    Log::info("ProcessCatalogChunk: Successfully synced " . count($meiliDocuments) . " items to Meilisearch index 'products'.");
                } else {
                    Log::warning("ProcessCatalogChunk: Meilisearch returned HTTP " . $response->status() . " — falling back to MySQL CatalogItem index.");
                }
            } catch (\Exception $e) {
                Log::notice("ProcessCatalogChunk: Local Meilisearch container not reachable ({$e->getMessage()}) — catalog stored in S3 MySQL DB.");
            }
        }
    }
}
