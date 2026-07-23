<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogWebhookController;
use App\Http\Controllers\Api\ProductDetailController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\ClickTrackerController;
use App\Http\Controllers\JwksController;
use App\Http\Middleware\CheckTokenBlacklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/jwks.json', [JwksController::class, 'index']);
Route::get('/oauth/jwks', [JwksController::class, 'index']);

// Global Search API & Product Catalog Endpoints (S2 Storefront / S3 index)
Route::match(['get', 'post'], '/v1/search', [SearchController::class, 'search']);
Route::get('/v1/products/{store_slug}/{product_slug}', [ProductDetailController::class, 'show']);

// S1 Catalog Webhook Endpoint
Route::post('/v1/webhooks/catalog', [CatalogWebhookController::class, 'handle']);

// Outbound Click Tracker Endpoint (S2 frontend / Marketplaces)
Route::get('/outbound', [ClickTrackerController::class, 'track']);
Route::get('/v1/clicks/track', [ClickTrackerController::class, 'track']);

// Authentication Routes
Route::post('/v1/auth/register', [AuthController::class, 'register']);
Route::post('/v1/auth/login', [AuthController::class, 'login']);
Route::post('/v1/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/v1/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:api', CheckTokenBlacklist::class])->group(function () {
    Route::post('/v1/auth/logout', [AuthController::class, 'logout']);
    Route::get('/v1/auth/profile', [AuthController::class, 'profile']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:api', CheckTokenBlacklist::class]);
