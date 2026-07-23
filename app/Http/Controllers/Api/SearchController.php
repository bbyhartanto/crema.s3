<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SearchController extends Controller
{
    /**
     * Search global catalog across all roasteries with faceted filters.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $origin = $request->input('origin');
        $roastLevel = $request->input('roast_level');
        $process = $request->input('process');
        $priceMin = $request->input('price_min');
        $priceMax = $request->input('price_max');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        // Attempt Meilisearch query first
        $meiliHost = env('MEILISEARCH_HOST', 'http://127.0.0.1:7700');
        $meiliKey = env('MEILISEARCH_KEY', 'masterKey');

        try {
            $filters = [];
            if (!empty($origin)) {
                $filters[] = "origin = '{$origin}'";
            }
            if (!empty($roastLevel)) {
                $filters[] = "roast_level = '{$roastLevel}'";
            }
            if (!empty($process)) {
                $filters[] = "process = '{$process}'";
            }
            if (!is_null($priceMin)) {
                $filters[] = "price_min >= {$priceMin}";
            }
            if (!is_null($priceMax)) {
                $filters[] = "price_max <= {$priceMax}";
            }

            $meiliPayload = [
                'q' => $q,
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
                'filter' => implode(' AND ', $filters),
                'facets' => ['origin', 'roast_level', 'process'],
                'attributesToSearchOn' => ['name', 'store_name', 'store_slug', 'origin', 'process', 'roast_level', 'flavor_notes'],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $meiliKey,
            ])->post("{$meiliHost}/indexes/products/search", $meiliPayload);

            if ($response->successful()) {
                $resData = $response->json();
                return response()->json([
                    'source' => 'meilisearch',
                    'query' => $q,
                    'total' => $resData['estimatedTotalHits'] ?? count($resData['hits'] ?? []),
                    'page' => $page,
                    'per_page' => $perPage,
                    'items' => $resData['hits'] ?? [],
                    'facets' => $resData['facetDistribution'] ?? [],
                ]);
            }
        } catch (\Exception $e) {
            // Fall back to MySQL catalog database
        }

        // Fallback to S3 MySQL CatalogItem database
        $query = CatalogItem::query()->where('is_available', true);

        if (!empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('origin', 'like', "%{$q}%")
                    ->orWhere('process', 'like', "%{$q}%")
                    ->orWhere('roast_level', 'like', "%{$q}%")
                    ->orWhere('store_name', 'like', "%{$q}%")
                    ->orWhere('store_slug', 'like', "%{$q}%");
            });
        }

        if (!empty($origin)) {
            $query->where('origin', $origin);
        }

        if (!empty($roastLevel)) {
            $query->where('roast_level', $roastLevel);
        }

        if (!empty($process)) {
            $query->where('process', $process);
        }

        if (!is_null($priceMin)) {
            $query->where('price_min', '>=', (float) $priceMin);
        }

        if (!is_null($priceMax)) {
            $query->where('price_max', '<=', (float) $priceMax);
        }

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        // Calculate available facets from MySQL data
        $facets = [
            'origin' => CatalogItem::distinct()->pluck('origin')->filter()->values(),
            'roast_level' => CatalogItem::distinct()->pluck('roast_level')->filter()->values(),
            'process' => CatalogItem::distinct()->pluck('process')->filter()->values(),
        ];

        return response()->json([
            'source' => 's3_database',
            'query' => $q,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'items' => $items,
            'facets' => $facets,
        ]);
    }
}
