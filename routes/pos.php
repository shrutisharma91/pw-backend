<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Pos\Http\Controllers\PosAuthController;
use App\Modules\Pos\Http\Controllers\PosDashboardController;
use App\Modules\Pos\Http\Controllers\PosLoanController;
use App\Modules\Pos\Http\Controllers\PosCatalogController;

/*
|--------------------------------------------------------------------------
| FinZ LMS — POS / Store Manager API Routes (Phase 5)
|--------------------------------------------------------------------------
|
| Handled under /api/v1 prefix implicitly by api.php
|
*/

Route::prefix('pos')->group(function () {
    // Phase 5 Screen 38 — Dedicated POS Login
    Route::post('/login', [PosAuthController::class, 'login']);
    Route::post('/verify-mfa', [PosAuthController::class, 'verifyMfa']);

    Route::middleware(['auth:api', 'scope.store'])->group(function () {
        Route::post('/logout', [PosAuthController::class, 'logout']);
        Route::get('/me', [PosAuthController::class, 'me']);

        // Dashboard
        Route::get('/dashboard', [PosDashboardController::class, 'index']);

        // Catalog
        Route::get('/catalog', [PosCatalogController::class, 'index']);

        // Loans (New Application & History)
        Route::post('/loans/initiate', [PosLoanController::class, 'initiate']); // Step 1: Customer details
        Route::post('/loans/calculate', [PosLoanController::class, 'calculate']); // Step 3: Fetch offers
        Route::post('/loans/submit', [PosLoanController::class, 'submit']); // Step 4: Submit application
        Route::get('/loans', [PosLoanController::class, 'index']); // History
        Route::get('/loans/{id}', [PosLoanController::class, 'show']); // Detail

        // Ledger (Screen 40)
        Route::get('/ledger', [\App\Modules\Pos\Http\Controllers\PosLedgerController::class, 'index']);

        // Staff Management (Screen 41)
        Route::get('/staff', [\App\Modules\Pos\Http\Controllers\PosStaffController::class, 'index']);
        Route::post('/staff', [\App\Modules\Pos\Http\Controllers\PosStaffController::class, 'store']);
        Route::post('/staff/{id}/toggle', [\App\Modules\Pos\Http\Controllers\PosStaffController::class, 'toggleActive']);

        // QR Standee (Screen 42)
        Route::get('/qr', [PosQrController::class, 'show']);
        Route::get('/qr/download-pdf', [PosQrController::class, 'downloadPdf']);
    });
});
