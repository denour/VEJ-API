<?php

use App\Http\Controllers\Api\BananaCallbackController;
use App\Http\Controllers\Api\V1\ContactMessageController;
use App\Http\Controllers\Api\V1\FaqController;
use App\Http\Controllers\Api\V1\NewsletterSubscriptionController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SpeciesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhooks
Route::post('webhooks/banana', [BananaCallbackController::class, 'handle'])
    ->middleware('throttle:30,1');

Route::prefix('v1')->middleware('throttle:public-api')->group(function (): void {
    // Settings
    Route::get('settings', [SettingsController::class, 'show']);
    // Posts
    Route::get('posts', [PostController::class, 'index']);
    Route::get('posts/{post:slug}', [PostController::class, 'show']);

    // FAQs
    Route::get('faqs', [FaqController::class, 'index']);
    Route::get('faqs/{faq}', [FaqController::class, 'show']);

    // Products
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    // Species
    Route::get('species', [SpeciesController::class, 'index']);
    Route::get('species/{species:slug}', [SpeciesController::class, 'show']);

    // Public writes: tighter per-IP limit to curb abuse / table flooding.
    Route::middleware('throttle:public-writes')->group(function (): void {
        // Newsletter
        Route::post('newsletter/subscribe', [NewsletterSubscriptionController::class, 'store']);

        // Contact
        Route::post('contact', [ContactMessageController::class, 'store']);
    });
});
