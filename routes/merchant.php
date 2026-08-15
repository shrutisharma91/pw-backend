<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Merchant\Http\Controllers\AuthController;
use App\Modules\Merchant\Http\Controllers\DashboardController;
use App\Modules\Merchant\Http\Controllers\ProductController;
use App\Modules\Merchant\Http\Controllers\SubventionController;
use App\Modules\Merchant\Http\Controllers\OfferController;
use App\Modules\Merchant\Http\Controllers\StoreController;
use App\Modules\Merchant\Http\Controllers\StaffController;
use App\Modules\Merchant\Http\Controllers\LoanController;
use App\Modules\Merchant\Http\Controllers\SettlementController;
use App\Modules\Merchant\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| FinZ LMS — Merchant Admin Portal Routes (Phase 4)
|--------------------------------------------------------------------------
|
| All routes are prefixed with /merchant in api.php and isolated by the
| scope.merchant middleware to ensure merchants can only view their data.
|
*/

Route::prefix('merchant')->group(function () {
    // Dedicated login for merchants
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/verify-mfa', [AuthController::class, 'verifyMfa']);

    // Protected Merchant Routes
    Route::middleware(['auth:api', 'scope.merchant'])->group(function () {
        
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Dashboard (Screen 27)
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Store Network Directory (Screens 28, 29)
        Route::apiResource('stores', StoreController::class);

        // Product Catalog (Screens 30, 31)
        Route::post('products/bulk-upload', [ProductController::class, 'bulkUpload']);
        Route::apiResource('products', ProductController::class);

        // Subvention Matrix Engine (Screen 32)
        Route::apiResource('subvention', SubventionController::class)->only(['index', 'update']);

        // Promotional Offer Builder (Screen 33)
        Route::apiResource('offers', OfferController::class);

        // Real-Time Loan Monitor (Screen 34)
        Route::get('loans', [LoanController::class, 'index']);

        // Disbursal Settlement Ledger (Screen 35)
        Route::get('settlements', [SettlementController::class, 'index']);
        Route::post('settlements/{id}/dispute', [SettlementController::class, 'dispute']);

        // Store User & Staff Management (Screen 36)
        Route::post('staff/{id}/reset-password', [StaffController::class, 'resetPassword']);
        Route::apiResource('staff', StaffController::class);

        // Merchant Analytics & Vault (Screen 37)
        Route::get('analytics/sales', [AnalyticsController::class, 'sales']);
        Route::get('analytics/vault', [AnalyticsController::class, 'vault']);
        Route::post('analytics/vault/upload', [AnalyticsController::class, 'vaultUpload']);
    });
});
