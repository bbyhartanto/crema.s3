<?php

use App\Http\Controllers\ClickTrackerController;
use App\Http\Controllers\JwksController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/jwks.json', [JwksController::class, 'index']);
Route::get('/oauth/jwks', [JwksController::class, 'index']);

Route::get('/outbound', [ClickTrackerController::class, 'track']);
Route::get('/r/{linkId?}', [ClickTrackerController::class, 'track']);

Route::get('/', function () {
    return response()->json([
        'service' => 'Crema S3 Identity Provider',
        'status' => 'online',
        'jwks_url' => url('/.well-known/jwks.json'),
    ]);
});
