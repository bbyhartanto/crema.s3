<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use Illuminate\Http\JsonResponse;

class ProductDetailController extends Controller
{
    /**
     * Get product catalog details by store slug and product slug from S3 index.
     */
    public function show(string $storeSlug, string $productSlug): JsonResponse
    {
        $product = CatalogItem::where('store_slug', $storeSlug)
            ->where('slug', $productSlug)
            ->first();

        if (!$product) {
            // Try matching product slug directly if store_slug differs or fallback
            $product = CatalogItem::where('slug', $productSlug)
                ->orWhere('id', $productSlug)
                ->first();
        }

        if (!$product) {
            return response()->json([
                'message' => 'Product not found in Crema S3 catalog index',
            ], 404);
        }

        return response()->json([
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'title' => $product->name,
                'name' => $product->name,
                'description' => $product->description,
                'base_price' => $product->price_min,
                'price_min' => $product->price_min,
                'price_max' => $product->price_max,
                'image_url' => $product->image_url,
                'thumbnail_url' => $product->image_url,
                'origin' => $product->origin,
                'roast_level' => $product->roast_level,
                'process' => $product->process,
                'flavor_notes' => $product->flavor_notes,
                'is_available' => $product->is_available,
                'store_id' => $product->store_id,
                'store_name' => $product->store_name,
                'store_slug' => $product->store_slug,
                'marketplace_links' => $product->marketplace_links ?? [],
                'field_values' => !empty($product->field_values) ? $product->field_values : [
                    ['field' => ['key' => 'origin'], 'formatted_value' => $product->origin],
                    ['field' => ['key' => 'region'], 'formatted_value' => $product->origin],
                    ['field' => ['key' => 'roast_level'], 'formatted_value' => $product->roast_level],
                    ['field' => ['key' => 'process'], 'formatted_value' => $product->process],
                    ['field' => ['key' => 'tasting_notes'], 'formatted_value' => is_array($product->flavor_notes) ? implode(', ', $product->flavor_notes) : (string) $product->flavor_notes],
                ],
            ]
        ]);
    }
}
