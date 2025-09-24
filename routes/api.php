<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'OK',
            'message' => 'API is running',
            'timestamp' => now()
        ]);
    });

    // Event routes
    Route::prefix('events')->group(function () {
        // GET endpoints
        Route::get('/', [EventController::class, 'index']);
        Route::get('/all', [EventController::class, 'getAll']);
        Route::get('/upcoming', [EventController::class, 'getUpcoming']);
        Route::get('/by-status', [EventController::class, 'getByStatus']);
        Route::get('/search', [EventController::class, 'search']);
        Route::get('/{event}', [EventController::class, 'show']);
        
        // POST endpoints
        Route::post('/', [EventController::class, 'store']);
        
        // PUT/PATCH endpoints
        Route::put('/{event}', [EventController::class, 'update']);
        Route::patch('/{event}', [EventController::class, 'update']);
        
        // DELETE endpoints
        Route::delete('/{event}', [EventController::class, 'destroy']);
    });
});