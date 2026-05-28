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

        // SSO placeholders
        Route::post('/sso/google', [LoginController::class, 'ssoGoogle']);
        Route::post('/sso/microsoft', [LoginController::class, 'ssoMicrosoft']);

        // Global MFA toggle (Project level)
        Route::post('/mfa/toggle', [LoginController::class, 'toggleGlobalMFA']);

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

        // Token refresh (call this before token expires)
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
            Route::put('/change-password', [ProfileController::class, 'changePassword']);
            Route::put('/mfa-setup', [ProfileController::class, 'mfaSetup']);          // Enable/disable/reconfigure MFA
            Route::put('/preferences', [ProfileController::class, 'updatePreferences']); // Theme, timezone, notif prefs
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
    Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']); // Screen 09 — force reset
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
    });
});
