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
use App\Http\Controllers\Api\ScheduleImportTemplateController;
use App\Http\Controllers\WelcomeScreenController;
use App\Http\Controllers\ProfessionController;
use App\Http\Controllers\Api\FeatureHighlightController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\AdminCustomerReportingTemplateController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminUserManagementController;

Route::prefix('v1')->group(function () {

    Route::get('health', function () {
        return response()->json([
            'status' => 'OK',
            'message' => 'API is running',
            'version' => '1.0.0',
            'timestamp' => now()
        ]);
    });

    // Public profession endpoints (for registration)
    Route::get('professions', [ProfessionController::class, 'index']);
    Route::get('professions/{id}', [ProfessionController::class, 'show']);

    // Admin-protected profession management
    Route::middleware('auth:api')->prefix('professions')->group(function () {
        Route::post('/', [ProfessionController::class, 'store']);
        Route::put('{id}', [ProfessionController::class, 'update']);
        Route::delete('{id}', [ProfessionController::class, 'destroy']);
    });

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

    // User management routes (require authentication)
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']); // Get all users (Admin only)
        Route::get('{id}', [UserController::class, 'show']); // Get specific user
    });

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

    Route::prefix('schedule-import-templates')->group(function () {
        // List and filter templates
        Route::get('/', [ScheduleImportTemplateController::class, 'index']);
        Route::post('/', [ScheduleImportTemplateController::class, 'store']);
        Route::get('{scheduleImportTemplate}', [ScheduleImportTemplateController::class, 'show']);
        Route::put('{scheduleImportTemplate}', [ScheduleImportTemplateController::class, 'update']);
        Route::delete('{scheduleImportTemplate}', [ScheduleImportTemplateController::class, 'destroy']);

        // Download endpoints
        Route::get('{scheduleImportTemplate}/download', [ScheduleImportTemplateController::class, 'download']);
        Route::get('{scheduleImportTemplate}/download-sample', [ScheduleImportTemplateController::class, 'downloadSample']);
        Route::get('{scheduleImportTemplate}/download-instructions', [ScheduleImportTemplateController::class, 'downloadInstructions']);

        // Profession-specific templates
        Route::get('profession/{professionId}', [ScheduleImportTemplateController::class, 'getByProfession']);

        // Update statistics
        Route::post('{scheduleImportTemplate}/statistics', [ScheduleImportTemplateController::class, 'updateStatistics']);
    });

    // Public feature highlights (no authentication required)
    Route::get('feature-highlights', [FeatureHighlightController::class, 'index']);
    Route::get('feature-highlights/{featureHighlight}', [FeatureHighlightController::class, 'show']);

    // Public welcome screen routes (no authentication required)
    Route::get('welcome-screen', [WelcomeScreenController::class, 'getActiveScreen']);
    Route::post('welcome-screens', [WelcomeScreenController::class, 'store']);

    // Admin feature highlights routes (authentication required)
    Route::middleware('auth:api')->prefix('feature-highlights')->group(function () {
        Route::post('/', [FeatureHighlightController::class, 'store']);
        Route::put('{featureHighlight}', [FeatureHighlightController::class, 'update']);
        Route::patch('{featureHighlight}', [FeatureHighlightController::class, 'update']);
        Route::delete('{featureHighlight}', [FeatureHighlightController::class, 'destroy']);
    });

    // Admin welcome screen routes (authentication required)
    Route::prefix('welcome-screens')->group(function () {
        Route::get('/', [WelcomeScreenController::class, 'index']);
        Route::get('{welcomeScreen}', [WelcomeScreenController::class, 'show']);
        Route::put('{welcomeScreen}', [WelcomeScreenController::class, 'update']);
        Route::delete('{welcomeScreen}', [WelcomeScreenController::class, 'destroy']);
        Route::post('{welcomeScreen}/activate', [WelcomeScreenController::class, 'activate']);
    });

    // Admin customer reporting templates (authentication required)
    Route::middleware('auth:api')->prefix('admin/customer-reporting-templates')->group(function () {
        // CRUD operations
        Route::get('/', [AdminCustomerReportingTemplateController::class, 'index']);
        Route::post('/', [AdminCustomerReportingTemplateController::class, 'store']);
        Route::get('{id}', [AdminCustomerReportingTemplateController::class, 'show']);
        Route::put('{id}', [AdminCustomerReportingTemplateController::class, 'update']);
        Route::delete('{id}', [AdminCustomerReportingTemplateController::class, 'destroy']);
        
        // Additional operations
        Route::post('{id}/generate-report', [AdminCustomerReportingTemplateController::class, 'generateReport']);
        Route::post('{id}/clone', [AdminCustomerReportingTemplateController::class, 'cloneTemplate']);
        Route::patch('{id}/toggle-active', [AdminCustomerReportingTemplateController::class, 'toggleActive']);
        
        // Statistics and utilities
        Route::get('stats/customers', [AdminCustomerReportingTemplateController::class, 'getCustomerStats']);
    });

    // AI Schedule Analysis routes (requires authentication)
    Route::middleware('auth:api')->prefix('ai-schedule')->group(function () {
        // Main analysis endpoints
        Route::post('analyze', [App\Http\Controllers\Api\AiScheduleAnalysisController::class, 'analyzeSchedule']);
        Route::get('analyses', [App\Http\Controllers\Api\AiScheduleAnalysisController::class, 'listAnalyses']);
        Route::get('analysis/{id}', [App\Http\Controllers\Api\AiScheduleAnalysisController::class, 'getAnalysis']);
        Route::post('analysis/{id}/approve', [App\Http\Controllers\Api\AiScheduleAnalysisController::class, 'approveAnalysis']);
        
        // Schedule slots management
        Route::get('slots', [App\Http\Controllers\Api\AiScheduleAnalysisController::class, 'getScheduleSlots']);
        Route::put('slots/{id}', [App\Http\Controllers\Api\AiScheduleAnalysisController::class, 'updateScheduleSlot']);
        Route::post('slots/{id}/confirm', [App\Http\Controllers\Api\AiScheduleAnalysisController::class, 'confirmSlot']);
        Route::post('slots/{id}/complete', [App\Http\Controllers\Api\AiScheduleAnalysisController::class, 'completeSlot']);
    });

    // Admin authentication routes
    Route::prefix('admin/auth')->group(function () {
        // Public admin routes (no authentication required)
        Route::post('login', [AdminAuthController::class, 'login']);
        
        // Protected admin routes (require admin authentication)
        Route::middleware('admin.auth')->group(function () {
            Route::get('me', [AdminAuthController::class, 'me']);
            Route::post('logout', [AdminAuthController::class, 'logout']);
            Route::post('refresh', [AdminAuthController::class, 'refresh']);
            Route::post('create', [AdminAuthController::class, 'createAdmin']);
            Route::get('admins', [AdminAuthController::class, 'listAdmins']);
        });
    });

    // Admin user management routes (require admin authentication)
    Route::middleware('admin.auth')->prefix('admin/users')->group(function () {
        // Password reset management
        Route::post('verify-for-reset', [AdminUserManagementController::class, 'verifyUserForReset']);
        Route::post('request-password-reset', [AdminUserManagementController::class, 'requestPasswordReset']);
        Route::post('{userId}/reset-password', [AdminUserManagementController::class, 'resetUserPassword']);
        Route::get('password-reset-requests', [AdminUserManagementController::class, 'listPasswordResetRequests']);
    });
});