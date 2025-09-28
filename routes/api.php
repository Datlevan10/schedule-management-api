<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ScheduleImportController;
use App\Http\Controllers\ScheduleTemplateController;
use App\Http\Controllers\ScheduleTemplatesController;
use App\Http\Controllers\UserSchedulePreferencesController;
use App\Http\Controllers\ParsingRulesController;

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
        Route::post('login', [AuthController::class, 'login'])->name('login');
        
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
    // SCHEDULE IMPORT ROUTES - /api/v1/schedule-imports/
    // ================================================================
    
    Route::middleware('auth:api')->prefix('schedule-imports')->group(function () {
        // Import management
        Route::get('/', [ScheduleImportController::class, 'index']);
        Route::post('/', [ScheduleImportController::class, 'store']);
        Route::get('statistics', [ScheduleImportController::class, 'statistics']);
        Route::get('{id}', [ScheduleImportController::class, 'show']);
        Route::delete('{id}', [ScheduleImportController::class, 'destroy']);
        
        // Import processing
        Route::post('{id}/process', [ScheduleImportController::class, 'process']);
        Route::post('{id}/convert', [ScheduleImportController::class, 'convert']);
        
        // Import entries
        Route::get('{id}/entries', [ScheduleImportController::class, 'entries']);
        Route::patch('entries/{id}', [ScheduleImportController::class, 'updateEntry']);
    });

    // ================================================================
    // SCHEDULE TEMPLATES (IMPORT) - /api/v1/schedule-templates/
    // ================================================================
    
    Route::middleware('auth:api')->prefix('schedule-templates')->group(function () {
        // Template browsing and download
        Route::get('/', [ScheduleTemplateController::class, 'index']);
        Route::get('search', [ScheduleTemplateController::class, 'search']);
        Route::get('statistics', [ScheduleTemplateController::class, 'statistics']);
        Route::get('defaults', [ScheduleTemplateController::class, 'defaultTemplates']);
        Route::get('my-profession', [ScheduleTemplateController::class, 'myProfessionTemplates']);
        Route::get('{id}', [ScheduleTemplateController::class, 'show']);
        
        // Template downloads and previews
        Route::get('{id}/download', [ScheduleTemplateController::class, 'download']);
        Route::get('{id}/preview', [ScheduleTemplateController::class, 'preview']);
        Route::post('{id}/rate', [ScheduleTemplateController::class, 'rate']);
        
        // Admin only - template file generation
        Route::post('{id}/generate', [ScheduleTemplateController::class, 'generateFiles']);
    });

    // ================================================================
    // SCHEDULE TEMPLATES (CRUD) - /api/v1/templates/
    // ================================================================
    
    Route::middleware('auth:api')->prefix('templates')->group(function () {
        // CRUD operations for schedule templates
        Route::get('/', [ScheduleTemplatesController::class, 'index']);
        Route::post('/', [ScheduleTemplatesController::class, 'store']);
        Route::get('{id}', [ScheduleTemplatesController::class, 'show']);
        Route::put('{id}', [ScheduleTemplatesController::class, 'update']);
        Route::delete('{id}', [ScheduleTemplatesController::class, 'destroy']);
        
        // Template field mapping validation
        Route::post('validate-mapping', [ScheduleTemplatesController::class, 'validateMapping']);
        Route::post('{id}/duplicate', [ScheduleTemplatesController::class, 'duplicate']);
    });

    // ================================================================
    // USER SCHEDULE PREFERENCES - /api/v1/preferences/
    // ================================================================
    
    Route::middleware('auth:api')->prefix('preferences')->group(function () {
        // User preferences management
        Route::get('/', [UserSchedulePreferencesController::class, 'show']);
        Route::post('/', [UserSchedulePreferencesController::class, 'store']);
        Route::put('/', [UserSchedulePreferencesController::class, 'update']);
        Route::delete('/', [UserSchedulePreferencesController::class, 'destroy']);
        
        // Preference components
        Route::post('keywords', [UserSchedulePreferencesController::class, 'addKeyword']);
        Route::delete('keywords/{keyword}', [UserSchedulePreferencesController::class, 'removeKeyword']);
        Route::post('field-mappings', [UserSchedulePreferencesController::class, 'updateFieldMapping']);
        Route::get('defaults', [UserSchedulePreferencesController::class, 'getDefaults']);
    });

    // ================================================================
    // PARSING RULES - /api/v1/parsing-rules/
    // ================================================================
    
    Route::middleware('auth:api')->prefix('parsing-rules')->group(function () {
        // CRUD operations for parsing rules
        Route::get('/', [ParsingRulesController::class, 'index']);
        Route::post('/', [ParsingRulesController::class, 'store']);
        Route::get('{id}', [ParsingRulesController::class, 'show']);
        Route::put('{id}', [ParsingRulesController::class, 'update']);
        Route::delete('{id}', [ParsingRulesController::class, 'destroy']);
        
        // Rule testing and validation
        Route::post('{id}/test', [ParsingRulesController::class, 'testRule']);
        Route::post('validate-pattern', [ParsingRulesController::class, 'validatePattern']);
        Route::get('by-profession/{professionId}', [ParsingRulesController::class, 'byProfession']);
        Route::get('by-type/{type}', [ParsingRulesController::class, 'byType']);
        
        // Bulk operations
        Route::post('bulk-activate', [ParsingRulesController::class, 'bulkActivate']);
        Route::post('bulk-deactivate', [ParsingRulesController::class, 'bulkDeactivate']);
    });

    // ================================================================
    // FUTURE API ENDPOINTS CAN BE ADDED HERE
    // ================================================================
    
});