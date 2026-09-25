<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ForwardController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OfferFormFieldController;
use App\Http\Controllers\Public\ForwardedCvController;
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
    Route::post('/offers/{offer}/reanalyze', [OfferController::class, 'reanalyze']);
    Route::get('/offers/{offer}/form-fields', [OfferFormFieldController::class, 'index']);
    Route::put('/offers/{offer}/form-fields', [OfferFormFieldController::class, 'update']);
    Route::get('/offers/{offer}/applications', [ApplicationController::class, 'index']);
    Route::get('/offers/{offer}/skills', [ApplicationController::class, 'skills']);
    Route::post('/offers/{offer}/applications/bulk-status', [ApplicationController::class, 'bulkStatus']);
    Route::post('/applications/{application}/reanalyze', [ApplicationController::class, 'reanalyze']);
    Route::patch('/applications/{application}/status', [ApplicationController::class, 'updateStatus']);
    Route::post('/applications/{application}/notes', [ApplicationController::class, 'addNote']);
    Route::post('/applications/{application}/reject', [ApplicationController::class, 'reject']);
    Route::get('/applications/{application}', [ApplicationController::class, 'show']);
    Route::get('/applications/{application}/events', [ApplicationController::class, 'events']);
    Route::post('/applications/{application}/interviews', [InterviewController::class, 'store']);
    Route::patch('/interviews/{interview}', [InterviewController::class, 'update']);
    Route::get('/applications/{application}/files/{key}', [ApplicationController::class, 'download'])
        ->where('key', '[a-z0-9_]+');
    Route::post('/forwards', [ForwardController::class, 'store']);
    Route::get('/forwards', [ForwardController::class, 'index']);
    Route::get('/forwards/{forward}', [ForwardController::class, 'show']);
});

Route::get('/public/offers/{token}', [PublicOfferController::class, 'show'])
    ->middleware(['throttle:30,1']);
Route::post('/public/offers/{token}/applications', [PublicApplicationController::class, 'store'])
    ->middleware(['throttle:5,1', 'throttle:30,60']);
Route::get('/public/forwards/cv/{application}', ForwardedCvController::class)
    ->name('forwarded-cv')
    ->middleware(['signed', 'throttle:30,1']);
