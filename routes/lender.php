<?php

use App\Modules\Lender\Http\Controllers\ApiConfigController;
use App\Modules\Lender\Http\Controllers\ApplicationQueueController;
use App\Modules\Lender\Http\Controllers\DashboardController;
use App\Modules\Lender\Http\Controllers\DisbursalBatchController;
use App\Modules\Lender\Http\Controllers\RepaymentMonitorController;
use App\Modules\Lender\Http\Controllers\RuleEngineController;
use App\Modules\Lender\Http\Controllers\SlaAnalyticsController;
use App\Modules\Lender\Http\Controllers\UnderwritingController;
use Illuminate\Support\Facades\Route;

/*
| Phase 3 — Lender Operations Portal API (Screens 19–26)
| Middleware stack §1.4
*/

Route::middleware(['auth:api', 'mfa.verified', 'lender.api', 'scope.lender'])
    ->prefix('lender')
    ->group(function () {

        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);

        Route::get('/applications/queue', [ApplicationQueueController::class, 'queue']);
        Route::get('/applications/{id}', [ApplicationQueueController::class, 'show'])->whereNumber('id');

        Route::get('/applications/{id}/credit-file', [UnderwritingController::class, 'creditFile'])->whereNumber('id');
        Route::post('/applications/{id}/decision', [UnderwritingController::class, 'decision'])->whereNumber('id');

        Route::get('/disbursals/batches', [DisbursalBatchController::class, 'index']);
        Route::post('/disbursals/batches/{id}/release', [DisbursalBatchController::class, 'release'])->whereNumber('id');

        Route::get('/rules', [RuleEngineController::class, 'index']);
        Route::post('/rules', [RuleEngineController::class, 'store']);
        Route::put('/rules/{id}', [RuleEngineController::class, 'update'])->whereNumber('id');
        Route::post('/rules/{id}/activate', [RuleEngineController::class, 'activate'])->whereNumber('id');
        Route::post('/rules/simulate', [RuleEngineController::class, 'simulate']);

        Route::get('/repayments/dpd-buckets', [RepaymentMonitorController::class, 'dpdBuckets']);
        Route::get('/repayments/delinquent', [RepaymentMonitorController::class, 'delinquent']);
        Route::post('/repayments/{loan_id}/tag-npa', [RepaymentMonitorController::class, 'tagNpa'])->whereNumber('loan_id');

        Route::get('/sla/metrics', [SlaAnalyticsController::class, 'metrics']);
        Route::get('/sla/scorecard/download', [SlaAnalyticsController::class, 'downloadScorecard']);

        Route::get('/api-config', [ApiConfigController::class, 'show']);
        Route::put('/api-config', [ApiConfigController::class, 'update']);
        Route::post('/api-config/test', [ApiConfigController::class, 'testConnection']);
    });
