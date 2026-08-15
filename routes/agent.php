<?php

use App\Modules\Agent\Http\Controllers\AuditController;
use App\Modules\Agent\Http\Controllers\CheckoutController;
use App\Modules\Agent\Http\Controllers\DashboardController;
use App\Modules\Agent\Http\Controllers\IncentiveController;
use App\Modules\Agent\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

/*
| Phase 6 — Sales Executive Field Portal API (Screens 43–47)
| Dedicated /api/v1/agent/* namespace, scoped to the logged-in sales_exec.
| Super Admin may pass ?sales_exec_id= for oversight.
*/

Route::middleware(['auth:api', 'mfa.verified', 'agent.api', 'scope.agent'])
    ->prefix('agent')
    ->group(function () {

        // Screen 43 — Field Lead & Pipeline Dashboard
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);

        // Screen 44 — On-Ground Merchant Onboarding
        Route::post('/merchant/verify-gstin', [OnboardingController::class, 'verifyGstin']);
        Route::post('/merchant/onboard', [OnboardingController::class, 'onboard']);
        Route::get('/merchants', [OnboardingController::class, 'index']);
        Route::get('/merchants/{id}', [OnboardingController::class, 'show'])->whereNumber('id');
        Route::put('/merchants/{id}', [OnboardingController::class, 'update'])->whereNumber('id');
        Route::post('/merchants/{id}/esign', [OnboardingController::class, 'initiateEsign'])->whereNumber('id');

        // Screen 45 — Assisted Field Customer Checkout
        Route::post('/checkout/send-otp', [CheckoutController::class, 'sendOtp']);
        Route::post('/checkout/verify-otp', [CheckoutController::class, 'verifyOtp']);
        Route::get('/checkout/products', [CheckoutController::class, 'products']);
        Route::post('/checkout/submit', [CheckoutController::class, 'submit']);

        // Screen 46 — Merchant Store Visit & Audit Logger
        Route::get('/stores', [AuditController::class, 'stores']);
        Route::get('/audits', [AuditController::class, 'index']);
        Route::post('/audits/checkin', [AuditController::class, 'checkin']);
        Route::post('/audits/{id}/escalate', [AuditController::class, 'escalate'])->whereNumber('id');

        // Screen 47 — Agent Incentive Payout Tracker
        Route::get('/incentives/statement', [IncentiveController::class, 'statement']);
        Route::get('/incentives/statement/download', [IncentiveController::class, 'download']);
    });
