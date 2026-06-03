<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MFAController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\VerificationLogController;
use App\Http\Controllers\Admin\MerchantAgreementController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\MerchantCategoryController;
use App\Http\Controllers\Admin\LenderController;
use App\Http\Controllers\Admin\LenderWaterfallController;
use App\Http\Controllers\Admin\LenderRuleController;
use App\Http\Controllers\Admin\LenderSlaController;
use App\Http\Controllers\EmiTypeController;
use App\Http\Controllers\TenureSlabController;
use App\Http\Controllers\OfferController;
/*
|--------------------------------------------------------------------------
| FinZ LMS — Super Admin API Routes
| Phase 1: Authentication, Profile, Notifications
|--------------------------------------------------------------------------
|
| All routes are versioned under /api/v1
| Public routes (no token needed): login, forgot-password, reset-password
| MFA routes: need token but MFA not yet verified
| Protected routes: need token + MFA verified
|
*/

Route::prefix('v1')->group(function () {

    // =========================================================
    // PUBLIC ROUTES — No token needed
    // Screen 01: Login, Screen 03: Forgot/Reset Password
    // =========================================================
    Route::prefix('auth')->group(function () {

        // Screen 01 — Login
        Route::post('/login', [LoginController::class, 'login']);




        // Screen 03 — Forgot Password
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
    });

    // =========================================================
    // MFA ROUTES — Token needed but MFA not yet verified
    // Screen 02: MFA Verification
    // =========================================================
    Route::prefix('auth')->middleware(['auth:api'])->group(function () {

        // Screen 02 — MFA
        Route::post('/mfa/verify', [MFAController::class, 'verify']);
        Route::post('/mfa/resend', [MFAController::class, 'resend']);



        // Global MFA Toggle
        Route::post('/mfa/toggle', [MFAController::class, 'toggleGlobal']);

        // Refresh Token
        Route::post('/refresh', [LoginController::class, 'refresh']);

        // Logout
        Route::post('/logout', [LoginController::class, 'logout']);
    });

    // =========================================================
    // PROTECTED ROUTES — Token + MFA verified required
    // Screen 04: Profile Settings
    // Screen 05: Notification Center
    // =========================================================
    Route::prefix('admin')->middleware(['auth:api', 'mfa.verified'])->group(function () {

        // ----- Screen 04: Profile & Personal Settings -----
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);           // Get profile
            Route::put('/', [ProfileController::class, 'update']);         // Update name/mobile/photo
        });

        // ----- Screen 05: Notification Center -----
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);               // Get all with tabs/filters
            Route::put('/read-all', [NotificationController::class, 'markAllRead']); // Mark all as read
            Route::put('/{id}/read', [NotificationController::class, 'markRead']);   // Mark one as read
            Route::put('/{id}/snooze', [NotificationController::class, 'snooze']);   // Snooze
            Route::put('/{id}/archive', [NotificationController::class, 'archive']); // Archive
            Route::delete('/{id}', [NotificationController::class, 'destroy']);      // Delete
        });
        Route::prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);           // Main KPIs
    Route::get('/live-stream', [DashboardController::class, 'liveStream']); // Live application stream
    Route::get('/action-tray', [DashboardController::class, 'actionTray']); // Pending approvals, SLA breaches, fraud flags
});
 
// Screen 07 — System Health & Monitoring
Route::prefix('system-health')->group(function () {
    Route::get('/', [SystemHealthController::class, 'index']);                        // Full health overview
    Route::get('/api-status', [SystemHealthController::class, 'apiStatus']);          // Per-service uptime
    Route::get('/queue-depth', [SystemHealthController::class, 'queueDepth']);        // Redis job counts
    Route::get('/integrations', [SystemHealthController::class, 'integrationStatus']); // Bureau, eSign, GST etc.
    Route::get('/error-logs', [SystemHealthController::class, 'errorLogs']);          // Recent error feed
    Route::post('/maintenance', [SystemHealthController::class, 'toggleMaintenance']); // Toggle maintenance mode
});
 
// Screen 08 — Global Search & Command Palette
Route::prefix('search')->group(function () {
    Route::get('/', [GlobalSearchController::class, 'search']);              // Main search
    Route::get('/recent', [GlobalSearchController::class, 'recentSearches']); // Recent items
    Route::post('/save', [GlobalSearchController::class, 'saveSearch']);      // Save a search
    Route::delete('/saved/{id}', [GlobalSearchController::class, 'deleteSavedSearch']);
});
 
// =========================================================
// PHASE 3 — User & Access Management
// Screen 09: User Directory
// Screen 10: Create / Edit User
// Screen 11: Role Management
// Screen 12: Permission Matrix
// Screen 13: Session & Device Management
// =========================================================
 
// Screen 09 + 10 — User Directory & Create/Edit User
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);                        // Screen 09 — list all users
    Route::post('/', [UserController::class, 'store']);                       // Screen 10 — create user
    Route::get('/{id}', [UserController::class, 'show']);                     // Screen 10 — get single user
    Route::put('/{id}', [UserController::class, 'update']);                   // Screen 10 — edit user
    Route::post('/{id}/disable', [UserController::class, 'disable']);         // Screen 09 — disable user
    Route::post('/{id}/enable', [UserController::class, 'enable']);           // Screen 09 — enable user
    Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
    Route::put('/{id}/change-password', [UserController::class, 'changePassword']);
    Route::post('/{id}/force-mfa', [UserController::class, 'forceMFA']);      // Screen 09 — force MFA setup
    Route::post('/bulk-disable', [UserController::class, 'bulkDisable']);     // Screen 09 — bulk disable
    Route::get('/export/csv', [UserController::class, 'exportCSV']);          // Screen 09 — export
    Route::post('/{id}/impersonate', [UserController::class, 'impersonate']); // Screen 09 — impersonate with audit
});
 
// Screen 11 — Role Management
Route::prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);           // List all roles
    Route::post('/', [RoleController::class, 'store']);          // Create role
    Route::get('/{id}', [RoleController::class, 'show']);        // Get role detail
    Route::put('/{id}', [RoleController::class, 'update']);      // Edit role
    Route::post('/{id}/clone', [RoleController::class, 'clone']); // Clone role
    Route::delete('/{id}', [RoleController::class, 'archive']);  // Archive role (soft delete)
});
 
// Screen 12 — Permission Matrix
Route::prefix('permissions')->group(function () {
    Route::get('/', [RoleController::class, 'permissionMatrix']);              // Full matrix
    Route::put('/roles/{id}', [RoleController::class, 'updatePermissions']);   // Update role permissions
    Route::get('/roles/{id}/diff/{compareId}', [RoleController::class, 'diffRoles']); // Compare two roles
    Route::post('/roles/{id}/rollback', [RoleController::class, 'rollback']);  // Rollback to previous matrix
});
 
// Screen 13 — Session & Device Management
Route::prefix('sessions')->group(function () {
    Route::get('/', [SessionController::class, 'index']);                          // All active sessions
    Route::post('/{id}/revoke', [SessionController::class, 'revoke']);             // Force logout one session
    Route::post('/users/{userId}/revoke-all', [SessionController::class, 'revokeAll']); // Logout all sessions for user
    Route::get('/suspicious', [SessionController::class, 'suspicious']);           // Flagged sessions
    Route::put('/ip-rules', [SessionController::class, 'updateIPRules']);          // IP allowlist/denylist
});

        // ----- Phase 4: Merchant Lifecycle -----
        Route::prefix('merchants')->group(function () {
            Route::get('/', [MerchantController::class, 'index']);               // Screen 14: Merchant Directory
            Route::post('/bulk-approve', [MerchantController::class, 'bulkApprove']);
            Route::post('/bulk-reject', [MerchantController::class, 'bulkReject']);
            Route::get('/export', [MerchantController::class, 'export']);
            Route::post('/bulk-re-kyc', [MerchantController::class, 'bulkReKyc']);
            Route::get('/{id}', [MerchantController::class, 'show']);            // Screen 16: Merchant 360 Profile
            Route::post('/{id}/approve', [MerchantController::class, 'approve']);// Screen 15: Approve
            Route::post('/{id}/reject', [MerchantController::class, 'reject']);  // Screen 15: Reject
            Route::post('/{id}/re-kyc', [MerchantController::class, 'reKyc']);   // Screen 19: Re-KYC
            Route::post('/{id}/suspend', [MerchantController::class, 'suspend']);// Screen 19: Suspend
            Route::post('/{id}/send-notice', [MerchantController::class, 'sendNotice']);
            Route::post('/{id}/escalate', [MerchantController::class, 'escalateToRisk']);
            Route::get('/{id}/verification-logs', [VerificationLogController::class, 'index']); // Screen 18
            Route::post('/{id}/verification-logs/{log_id}/retry', [VerificationLogController::class, 'retry']);
            Route::post('/{id}/agreement', [MerchantAgreementController::class, 'generate']); // Screen 17
        });

        // ----- Phase 5: Store & Product Oversight -----
        Route::prefix('stores')->group(function () {
            Route::get('/', [StoreController::class, 'index']);               // Screen 20
            Route::get('/export', [StoreController::class, 'export']);        // Screen 20
            Route::get('/{id}', [StoreController::class, 'show']);            // Screen 21
            Route::post('/{id}/deactivate', [StoreController::class, 'deactivate']); // Screen 21
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);             // Screen 22
            Route::post('/bulk-financing-toggle', [ProductController::class, 'bulkFinancingToggle']); // Screen 22
            Route::post('/{id}/flag', [ProductController::class, 'flag']);    // Screen 22
            Route::post('/{id}/delist', [ProductController::class, 'delist']); // Screen 22
        });

        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);            // Screen 23
            Route::post('/', [CategoryController::class, 'store']);           // Screen 23
            Route::put('/{id}', [CategoryController::class, 'update']);       // Screen 23
            Route::post('/{id}/archive', [CategoryController::class, 'archive']); // Screen 23
            Route::put('/{id}/financing-rules', [CategoryController::class, 'setFinancingRules']); // Screen 23
        });

        Route::prefix('brands')->group(function () {
            Route::get('/', [BrandController::class, 'index']);               // Screen 23
            Route::post('/', [BrandController::class, 'store']);              // Screen 23
            Route::put('/{id}', [BrandController::class, 'update']);          // Screen 23
        });

        Route::prefix('merchant-categories')->group(function () {
            Route::post('/{id}/map', [MerchantCategoryController::class, 'mapToMaster']); // Screen 23
        });

        // ----- Phase 6: Lender Operations -----
        Route::prefix('lenders')->group(function () {
            Route::get('/', [LenderController::class, 'index']);             // Screen 24
            Route::get('/{id}', [LenderController::class, 'show']);           // Screen 25
            Route::post('/', [LenderController::class, 'store']);             // Screen 25
            Route::put('/{id}', [LenderController::class, 'update']);         // Screen 25
            Route::post('/{id}/toggle', [LenderController::class, 'toggle']); // Screen 24
            Route::post('/{id}/test-connection', [LenderController::class, 'testConnection']); // Screen 25
        });

        Route::prefix('lender-waterfalls')->group(function () {
            Route::get('/', [LenderWaterfallController::class, 'index']);     // Screen 26
            Route::post('/', [LenderWaterfallController::class, 'store']);    // Screen 26
            Route::put('/{id}', [LenderWaterfallController::class, 'update']);// Screen 26
            Route::post('/simulate', [LenderWaterfallController::class, 'simulate']); // Screen 26
        });

        Route::prefix('lender-rules')->group(function () {
            Route::get('/', [LenderRuleController::class, 'index']);          // Screen 27
            Route::post('/', [LenderRuleController::class, 'store']);         // Screen 27
            Route::put('/{id}', [LenderRuleController::class, 'update']);     // Screen 27
            Route::post('/{id}/archive', [LenderRuleController::class, 'archive']); // Screen 27
        });

        Route::prefix('lender-sla')->group(function () {
            Route::get('/metrics', [LenderSlaController::class, 'index']);    // Screen 28
            Route::get('/export', [LenderSlaController::class, 'export']);    // Screen 28
        });

        // ----- Phase 7: Pricing & Offers -----
        Route::prefix('pricing')->group(function () {
            // Screen 29: EMI Master Configuration
            Route::apiResource('emi-types', EmiTypeController::class);
            
            // Screen 30: Tenure & Interest Slabs
            Route::prefix('tenure-slabs')->group(function () {
                Route::get('/export', [TenureSlabController::class, 'exportCsv']);
                Route::post('/import', [TenureSlabController::class, 'importCsv']);
            });
            Route::apiResource('tenure-slabs', TenureSlabController::class);
        });

        Route::prefix('offers')->group(function () {
            // Screen 31: Offer Engine Builder
            Route::get('/', [OfferController::class, 'index']);
            Route::post('/', [OfferController::class, 'store']);
            Route::get('/{id}', [OfferController::class, 'show']);
            Route::put('/{id}', [OfferController::class, 'update']);
            Route::delete('/{id}', [OfferController::class, 'destroy']);
            
            // Screen 32: Offer Approval Queue
            Route::post('/{id}/approve', [OfferController::class, 'approve']);
            Route::post('/{id}/reject', [OfferController::class, 'reject']);
        });
    });
});
