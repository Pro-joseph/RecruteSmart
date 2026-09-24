<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OfferFormFieldController;
use App\Http\Controllers\Public\PublicApplicationController;
use App\Http\Controllers\Public\PublicOfferController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'updateMe']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/form-field-catalog', [OfferFormFieldController::class, 'catalog']);
    Route::apiResource('offers', OfferController::class);
    Route::post('/offers/{offer}/publish', [OfferController::class, 'publish']);
    Route::post('/offers/{offer}/close', [OfferController::class, 'close']);
    Route::post('/offers/{offer}/duplicate', [OfferController::class, 'duplicate']);
    Route::post('/offers/{offer}/regenerate-link', [OfferController::class, 'regenerateLink']);
    Route::get('/offers/{offer}/form-fields', [OfferFormFieldController::class, 'index']);
    Route::put('/offers/{offer}/form-fields', [OfferFormFieldController::class, 'update']);
});

Route::get('/public/offers/{token}', [PublicOfferController::class, 'show'])
    ->middleware(['throttle:30,1']);
Route::post('/public/offers/{token}/applications', [PublicApplicationController::class, 'store'])
    ->middleware(['throttle:5,1', 'throttle:30,60']);
