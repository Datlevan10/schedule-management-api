<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;

// ================================================================
// UNIFIED API v1 ROUTES - All routes under /api/v1/
// ================================================================

Route::prefix('v1')->group(function () {
    
    // ================================================================
    // HEALTH CHECK
    // ================================================================
    Route::get('health', function () {
        return response()->json([
            'status' => 'OK',
            'message' => 'API is running',
            'version' => '1.0.0',
            'timestamp' => now()
        ]);
    });

    // ================================================================
    // AUTHENTICATION ROUTES - /api/v1/auth/
    // ================================================================
    
    // Public authentication routes (no middleware required)
    Route::prefix('auth')->group(function () {
        // User Registration
        Route::post('register', [AuthController::class, 'register']);
        
        // User Login
        Route::post('login', [AuthController::class, 'login']);
        
        // Password Reset Routes
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        
        // Email Verification
        Route::post('email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('email/resend', [AuthController::class, 'resendVerificationEmail']);
        
        // Account Reactivation (for deactivated accounts)
        Route::post('reactivate', [AuthController::class, 'reactivateAccount']);
    });

    // Protected authentication routes (require authentication)
    Route::middleware('auth:api')->prefix('auth')->group(function () {
        // User Logout
        Route::post('logout', [AuthController::class, 'logout']);
        
        // Token Refresh
        Route::post('refresh', [AuthController::class, 'refresh']);
        
        // Get Current User Profile
        Route::get('me', [AuthController::class, 'me']);
        
        // Update Profile
        Route::put('profile', [AuthController::class, 'updateProfile']);
        
        // Change Password
        Route::post('change-password', [AuthController::class, 'changePassword']);
        
        // Account Management
        Route::post('deactivate', [AuthController::class, 'deactivateAccount']);
        Route::delete('delete', [AuthController::class, 'deleteAccount']);
    });

    // ================================================================
    // EVENT ROUTES - /api/v1/events/
    // ================================================================
    
    Route::prefix('events')->group(function () {
        // GET endpoints
        Route::get('/', [EventController::class, 'index']);
        Route::get('all', [EventController::class, 'getAll']);
        Route::get('upcoming', [EventController::class, 'getUpcoming']);
        Route::get('by-status', [EventController::class, 'getByStatus']);
        Route::get('search', [EventController::class, 'search']);
        Route::get('{event}', [EventController::class, 'show']);
        
        // POST endpoints
        Route::post('/', [EventController::class, 'store']);
        
        // PUT/PATCH endpoints
        Route::put('{event}', [EventController::class, 'update']);
        Route::patch('{event}', [EventController::class, 'update']);
        
        // DELETE endpoints
        Route::delete('{event}', [EventController::class, 'destroy']);
    });

    // ================================================================
    // FUTURE API ENDPOINTS CAN BE ADDED HERE
    // ================================================================
    
    // Example: Professions endpoint
    // Route::prefix('professions')->group(function () {
    //     Route::get('/', [ProfessionController::class, 'index']);
    // });
    
});