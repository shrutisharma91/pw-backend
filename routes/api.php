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
use App\Http\Controllers\Admin\BusinessAnalyticsController;
use App\Http\Controllers\Admin\LenderAnalyticsController;
use App\Http\Controllers\Admin\SalesAnalyticsController;
use App\Http\Controllers\Admin\CustomReportController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\CommunicationLogController;
use App\Http\Controllers\Admin\DocumentRepositoryController;
use App\Http\Controllers\Admin\WorkflowBuilderController;
use App\Http\Controllers\Admin\IntegrationSwitchboardController;
use App\Http\Controllers\Admin\FeatureFlagController;
use App\Http\Controllers\Admin\SystemParameterController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\LoanApplicationController;
use App\Http\Controllers\Admin\ManualOverrideController;
use App\Http\Controllers\Admin\DisbursalSettlementController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\FraudAlertController;
use App\Http\Controllers\Admin\BlacklistController;
use App\Http\Controllers\Admin\RiskRuleController;
use App\Http\Controllers\Admin\ManualReviewController;
use App\Http\Controllers\Admin\AuditTrailController;
use App\Http\Controllers\Admin\ConsentLogController;
use App\Http\Controllers\Admin\ComplianceReportController;
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
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            // POST alias: PHP does not parse multipart/form-data on PUT, so file
            // uploads (profile photo) must use POST (optionally with _method=PUT).
            Route::post('/', [ProfileController::class, 'update']);
            Route::post('/change-password', [ProfileController::class, 'changePassword']);

            // MFA reconfigure & recovery codes
            Route::get('/mfa', [ProfileController::class, 'mfaStatus']);
            Route::post('/mfa/setup', [ProfileController::class, 'mfaSetup']);
            Route::post('/mfa/confirm', [ProfileController::class, 'mfaConfirm']);
            Route::post('/mfa/use-email', [ProfileController::class, 'mfaUseEmail']);
            Route::post('/recovery-codes/regenerate', [ProfileController::class, 'regenerateRecoveryCodes']);
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
    Route::post('/bulk-revoke', [SessionController::class, 'bulkRevoke']);          // Bulk revoke sessions
    Route::post('/revoke-all-suspicious', [SessionController::class, 'revokeAllSuspicious']); // Revoke ALL suspicious sessions platform-wide
    Route::get('/suspicious', [SessionController::class, 'suspicious']);           // Flagged sessions
    Route::put('/ip-rules', [SessionController::class, 'updateIPRules']);          // IP allowlist/denylist
    Route::post('/users/{userId}/revoke-all', [SessionController::class, 'revokeAll']); // Logout all sessions for user
    Route::post('/{id}/revoke', [SessionController::class, 'revoke']);             // Force logout one session
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
            Route::get('/{id}/agreements', [MerchantAgreementController::class, 'index']); // Screen 17
            Route::post('/{id}/agreement', [MerchantAgreementController::class, 'generate']); // Screen 17
            Route::get('/{id}/agreements/{agreement_id}/preview', [MerchantAgreementController::class, 'preview']); // Screen 17
            Route::get('/{id}/agreements/{agreement_id}/esign-status', [MerchantAgreementController::class, 'esignStatus']); // Screen 17
            Route::get('/{id}/documents', [MerchantController::class, 'documents']);
            Route::get('/{id}/documents/{document_id}/view', [MerchantController::class, 'documentViewer']);
            Route::get('/{id}/notes', [MerchantController::class, 'notes']);
            Route::post('/{id}/notes', [MerchantController::class, 'addNote']);
            Route::post('/{id}/ephemeral-notes', [MerchantController::class, 'ephemeralNotes']);
            Route::post('/{id}/approve-changes', [MerchantController::class, 'approveChanges']);
            Route::post('/{id}/reactivate', [MerchantController::class, 'reactivate']);
        });

        Route::prefix('verifications')->group(function () {
            Route::post('/provider-switch', [VerificationLogController::class, 'switchProvider']);
        });

        // ----- Phase 5: Store & Product Oversight -----
        Route::prefix('stores')->group(function () {
            Route::get('/', [StoreController::class, 'index']);               // Screen 20
            Route::get('/export', [StoreController::class, 'export']);        // Screen 20
            Route::get('/{id}', [StoreController::class, 'show']);            // Screen 21
            Route::get('/{id}/linked-products', [StoreController::class, 'linkedProducts']);
            Route::get('/{id}/loan-applications', [StoreController::class, 'loanApplications']);
            Route::post('/{id}/deactivate', [StoreController::class, 'deactivate']); // Screen 21
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);             // Screen 22
            Route::post('/bulk-import', [ProductController::class, 'bulkImport']); // Bulk Import for Products
            Route::post('/detect-duplicates', [ProductController::class, 'detectDuplicates']); // Screen 22
            Route::post('/bulk-financing-toggle', [ProductController::class, 'bulkFinancingToggle']); // Screen 22
            Route::post('/{id}/flag', [ProductController::class, 'flag']);    // Screen 22
            Route::post('/{id}/delist', [ProductController::class, 'delist']); // Screen 22
        });

        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index']);            // Screen 23
            Route::get('/export', [CategoryController::class, 'export']);     // Screen 23
            Route::post('/import', [CategoryController::class, 'import']);    // Screen 23
            Route::post('/', [CategoryController::class, 'store']);           // Screen 23
            Route::put('/{id}', [CategoryController::class, 'update']);       // Screen 23
            Route::post('/{id}/archive', [CategoryController::class, 'archive']); // Screen 23
            Route::put('/{id}/financing-rules', [CategoryController::class, 'setFinancingRules']); // Screen 23
        });

        Route::prefix('brands')->group(function () {
            Route::get('/', [BrandController::class, 'index']);               // Screen 23
            Route::get('/export', [BrandController::class, 'export']);        // Screen 23
            Route::post('/import', [BrandController::class, 'import']);       // Screen 23
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
            Route::get('/trends', [LenderSlaController::class, 'trends']);    // Screen 28
            Route::get('/export', [LenderSlaController::class, 'export']);    // Screen 28
            Route::get('/{id}/history', [LenderSlaController::class, 'history']); // Screen 28
            Route::get('/{id}/breakdown', [LenderSlaController::class, 'breakdown']); // Screen 28
        });

        // ----- Phase 7: Pricing & Offers -----
        Route::prefix('pricing')->group(function () {
            // Screen 29: EMI Master Configuration
            Route::post('/emi-types/{id}/toggle', [EmiTypeController::class, 'toggle']);
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
            Route::get('/pending', [OfferController::class, 'pending']);
            Route::get('/{id}', [OfferController::class, 'show']);
            Route::put('/{id}', [OfferController::class, 'update']);
            Route::delete('/{id}', [OfferController::class, 'destroy']);
            
            // Screen 32: Offer Approval Queue
            Route::post('/{id}/approve', [OfferController::class, 'approve']);
            Route::post('/{id}/reject', [OfferController::class, 'reject']);
        });
        // ----- Loan & Disbursal Management -----
        Route::prefix('loans')->group(function () {
            // Screen 33: Monitor
            Route::get('/', [LoanApplicationController::class, 'index']);
            Route::get('/export', [LoanApplicationController::class, 'export']);
            Route::get('/saved-filters', [LoanApplicationController::class, 'getSavedFilters']);
            Route::post('/saved-filters', [LoanApplicationController::class, 'saveFilter']);
            
            // Screen 34: Detail & Timeline
            Route::get('/{id}', [LoanApplicationController::class, 'show']);
            Route::get('/{id}/timeline', [LoanApplicationController::class, 'timeline']);
            Route::get('/{id}/documents', [LoanApplicationController::class, 'documents']);
            Route::get('/{id}/communications', [LoanApplicationController::class, 'communications']);
        });

        Route::prefix('loans/overrides')->group(function () {
            // Screen 35: Manual Override Console
            Route::post('/{id}/force-approve', [ManualOverrideController::class, 'forceApprove']);
            Route::post('/{id}/override-rejection', [ManualOverrideController::class, 'overrideRejection']);
            Route::post('/{id}/trigger-disbursal', [ManualOverrideController::class, 'triggerDisbursal']);
            Route::post('/{id}/refund', [ManualOverrideController::class, 'refund']);
        });

        Route::prefix('disbursals')->group(function () {
            // Screen 36: Disbursal Queue
            Route::get('/pending', [DisbursalSettlementController::class, 'pendingDisbursals']);
            Route::post('/trigger-batch', [DisbursalSettlementController::class, 'triggerBatchDisbursal']);
        });

        Route::prefix('settlements')->group(function () {
            // Screen 36: Settlement Reconciliation
            Route::get('/batches', [DisbursalSettlementController::class, 'settlementBatches']);
            Route::get('/batches/{batch_id}/entries', [DisbursalSettlementController::class, 'settlementEntries']);
            Route::get('/batches/{batch_id}/download', [DisbursalSettlementController::class, 'downloadSettlement']);
            Route::post('/entries/{entry_id}/dispute', [DisbursalSettlementController::class, 'disputeSettlement']);
        });

        Route::prefix('collections')->group(function () {
            // Screen 37: Collections & Bounce Management
            Route::get('/', [CollectionController::class, 'index']);
            Route::post('/{id}/assign-agent', [CollectionController::class, 'assignAgent']);
            Route::post('/{id}/npa-status', [CollectionController::class, 'setNpaStatus']);
            
            Route::get('/bounces', [CollectionController::class, 'bounceFeed']);
            Route::post('/bounces/{id}/retry', [CollectionController::class, 'retryBounce']);
        });

        // ----- Risk & Fraud -----
        Route::prefix('fraud-alerts')->group(function () {
            // Screen 38: Fraud Alert Feed
            Route::get('/', [FraudAlertController::class, 'index']);
            Route::post('/{id}/block', [FraudAlertController::class, 'block']);
            Route::post('/{id}/unblock', [FraudAlertController::class, 'unblock']);
            Route::post('/{id}/escalate', [FraudAlertController::class, 'escalate']);
            Route::get('/stats/heatmap', [FraudAlertController::class, 'heatmap']);
        });

        Route::prefix('blacklist')->group(function () {
            // Screen 39: Blacklist Manager
            Route::get('/', [BlacklistController::class, 'index']);
            Route::post('/', [BlacklistController::class, 'store']);
            Route::post('/bulk-import', [BlacklistController::class, 'bulkImport']);
            Route::post('/{id}/remove', [BlacklistController::class, 'remove']);
            Route::post('/{id}/whitelist-override', [BlacklistController::class, 'whitelistOverride']);
        });

        Route::prefix('risk-rules')->group(function () {
            // Screen 40: Velocity & Risk Rules
            Route::get('/', [RiskRuleController::class, 'index']);
            Route::post('/', [RiskRuleController::class, 'store']);
            Route::put('/{id}', [RiskRuleController::class, 'update']);
            Route::post('/simulate', [RiskRuleController::class, 'simulate']);
        });

        Route::prefix('manual-reviews')->group(function () {
            // Screen 41: Manual Review Queue
            Route::get('/', [ManualReviewController::class, 'index']);
            Route::get('/{id}', [ManualReviewController::class, 'show']);
            Route::post('/{id}/decide', [ManualReviewController::class, 'decide']);
            Route::get('/scorecard/{reviewer_id}', [ManualReviewController::class, 'scorecard']);
        });

        // ----- Compliance & Audit -----
        Route::prefix('audit-trails')->group(function () {
            // Screen 42: Audit Trail Explorer
            Route::get('/', [AuditTrailController::class, 'index']);
            Route::get('/export', [AuditTrailController::class, 'export']);
            Route::get('/anomalies', [AuditTrailController::class, 'detectAnomalies']);
            Route::post('/verify-hash', [AuditTrailController::class, 'verifyHashChain']);
        });

        Route::prefix('consents')->group(function () {
            // Screen 43: Consent Log Viewer
            Route::get('/', [ConsentLogController::class, 'index']);
            Route::post('/{id}/withdraw', [ConsentLogController::class, 'withdraw']);
            Route::get('/{id}/diff/{compare_id}', [ConsentLogController::class, 'diff']);
            Route::get('/export', [ConsentLogController::class, 'export']);
        });

        Route::prefix('compliance')->group(function () {
            // Screen 44: Compliance Reports & Exports
            Route::post('/returns', [ComplianceReportController::class, 'generateReturn']);
            
            Route::get('/dpdp-requests', [ComplianceReportController::class, 'dpdpRequests']);
            Route::post('/dpdp-requests/{id}/resolve', [ComplianceReportController::class, 'resolveDpdpRequest']);
            
            Route::get('/data-masking-policy', [ComplianceReportController::class, 'dataMaskingPolicy']);
            Route::post('/data-masking-policy', [ComplianceReportController::class, 'updateDataMaskingPolicy']);
            Route::get('/retention-policy', [ComplianceReportController::class, 'retentionPolicy']);
            Route::post('/retention-policy', [ComplianceReportController::class, 'updateRetentionPolicy']);
            Route::get('/dashboard', [ComplianceReportController::class, 'dashboard']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | FinZ LMS — Super Admin API Routes: Phase 11, 12, 13, 14
    |--------------------------------------------------------------------------
    |
    | All routes are guarded by:
    |   - auth:admin      (JWT token issued at login)
    |   - admin.mfa       (MFA verified in this session)
    |   - throttle:60,1   (60 requests / minute per user)
    |
    | Module-level permission gates are applied inside each controller.
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->middleware(['auth:api', 'mfa.verified', 'throttle:60,1'])
        ->group(function () {

            // ─────────────────────────────────────────────────────────────────────
            // PHASE 11 — Analytics & Business Intelligence
            // Screens 45 (Business Analytics), 46 (Lender Analytics),
            //         47 (Sales Analytics),    48 (Custom Report Builder)
            // ─────────────────────────────────────────────────────────────────────

            Route::prefix('analytics')->group(function () {

                // Screen 45 — Business Analytics Dashboard
                Route::prefix('business')->group(function () {
                    Route::get('/',          [BusinessAnalyticsController::class, 'index']);
                    Route::post('/snapshot', [BusinessAnalyticsController::class, 'saveSnapshot']);
                    Route::get('/snapshots', [BusinessAnalyticsController::class, 'listSnapshots']);
                });

                // Screen 46 — Lender & Loan Analytics
                Route::prefix('lender')->group(function () {
                    Route::get('/',                [LenderAnalyticsController::class, 'index']);
                    Route::get('/{id}/scorecard', [LenderAnalyticsController::class, 'scorecard'])->whereNumber('id');
                    Route::post('/export',         [LenderAnalyticsController::class, 'export']);
                });

                // Screen 47 — Sales & Region Analytics
                Route::prefix('sales')->group(function () {
                    Route::get('/',                          [SalesAnalyticsController::class, 'index']);
                    Route::get('/region/{state}/stores',     [SalesAnalyticsController::class, 'regionStores']);
                    Route::get('/exec/{id}/pipeline',        [SalesAnalyticsController::class, 'execPipeline'])->whereNumber('id');
                });
            });

            // Screen 48 — Custom Report Builder
            Route::prefix('reports/custom')->group(function () {
                Route::get('/schema',               [CustomReportController::class, 'schema']);
                Route::get('/',                     [CustomReportController::class, 'index']);
                Route::post('/',                    [CustomReportController::class, 'run']);
                Route::post('/save',                [CustomReportController::class, 'save']);
                Route::put('/{id}',                 [CustomReportController::class, 'update'])->whereNumber('id');
                Route::post('/{id}/schedule',       [CustomReportController::class, 'schedule'])->whereNumber('id');
                Route::post('/{id}/export',         [CustomReportController::class, 'export'])->whereNumber('id');
                Route::get('/{id}/history',         [CustomReportController::class, 'history'])->whereNumber('id');
                Route::delete('/{id}',              [CustomReportController::class, 'destroy'])->whereNumber('id');
            });


            // ─────────────────────────────────────────────────────────────────────
            // PHASE 12 — Notifications & Document Management
            // Screens 49 (Templates), 50 (Comm Logs), 51 (Document Repository)
            // ─────────────────────────────────────────────────────────────────────

            // Screen 49 — Notification Template Manager
            Route::prefix('templates')->group(function () {
                Route::get('/variables',              [NotificationTemplateController::class, 'availableVariables']);
                Route::get('/',                       [NotificationTemplateController::class, 'index']);
                Route::post('/',                      [NotificationTemplateController::class, 'store']);
                Route::get('/{id}',                   [NotificationTemplateController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',                   [NotificationTemplateController::class, 'update'])->whereNumber('id');
                Route::post('/{id}/activate',         [NotificationTemplateController::class, 'activate'])->whereNumber('id');
                Route::post('/{id}/archive',          [NotificationTemplateController::class, 'archive'])->whereNumber('id');
                Route::post('/{id}/rollback/{version}',[NotificationTemplateController::class, 'rollback'])->whereNumber('id')->whereNumber('version');
                Route::get('/{id}/diff/{v1}/{v2}',    [NotificationTemplateController::class, 'diff'])->whereNumber('id')->whereNumber('v1')->whereNumber('v2');
                Route::post('/{id}/test-send',        [NotificationTemplateController::class, 'testSend'])->whereNumber('id');
            });

            // Screen 50 — Communication Logs
            Route::prefix('communication-logs')->group(function () {
                Route::get('/',                      [CommunicationLogController::class, 'index']);
                Route::get('/stats/summary',         [CommunicationLogController::class, 'summary']);
                Route::get('/stats/daily-trend',     [CommunicationLogController::class, 'dailyTrend']);
                Route::get('/{id}',                  [CommunicationLogController::class, 'show'])->whereNumber('id');
                Route::post('/resend',               [CommunicationLogController::class, 'resend']);
            });

            // Screen 51 — Document Repository
            Route::prefix('documents')->group(function () {
                Route::get('/stats',              [DocumentRepositoryController::class, 'stats']);
                Route::get('/',                   [DocumentRepositoryController::class, 'index']);
                Route::post('/',                  [DocumentRepositoryController::class, 'store']);
                Route::get('/{id}',               [DocumentRepositoryController::class, 'show'])->whereNumber('id');
                Route::get('/{id}/preview',       [DocumentRepositoryController::class, 'preview'])->whereNumber('id');
                Route::post('/{id}/share',        [DocumentRepositoryController::class, 'share'])->whereNumber('id');
                Route::post('/{id}/ocr-rerun',    [DocumentRepositoryController::class, 'rerunOcr'])->whereNumber('id');
                Route::put('/{id}/retention',     [DocumentRepositoryController::class, 'updateRetention'])->whereNumber('id');
                Route::delete('/{id}',            [DocumentRepositoryController::class, 'destroy'])->whereNumber('id');
            });


            // ─────────────────────────────────────────────────────────────────────
            // PHASE 13 — System & Integrations
            // Screens 52 (Workflow Builder), 53 (Integration Switchboard),
            //         54 (Feature Flags),    55 (System Parameters)
            // ─────────────────────────────────────────────────────────────────────

            // Screen 52 — Workflow Builder
            Route::prefix('workflows')->group(function () {
                Route::get('/templates',               [WorkflowBuilderController::class, 'templates']);
                Route::get('/',                        [WorkflowBuilderController::class, 'index']);
                Route::post('/',                       [WorkflowBuilderController::class, 'store']);
                Route::get('/{id}',                    [WorkflowBuilderController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',                    [WorkflowBuilderController::class, 'update'])->whereNumber('id');
                Route::post('/{id}/publish',           [WorkflowBuilderController::class, 'publish'])->whereNumber('id');
                Route::post('/{id}/archive',           [WorkflowBuilderController::class, 'archive'])->whereNumber('id');
                Route::get('/{id}/versions',           [WorkflowBuilderController::class, 'versions'])->whereNumber('id');
                Route::post('/{id}/rollback/{version}',[WorkflowBuilderController::class, 'rollback'])->whereNumber('id')->whereNumber('version');
            });

            // Screen 53 — Third-Party Integration Switchboard
            Route::prefix('integrations')->group(function () {
                Route::get('/billing/summary',             [IntegrationSwitchboardController::class, 'billingSummary']);
                Route::get('/',                            [IntegrationSwitchboardController::class, 'index']);
                Route::get('/{id}',                        [IntegrationSwitchboardController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',                        [IntegrationSwitchboardController::class, 'update'])->whereNumber('id');
                Route::post('/{id}/toggle',                [IntegrationSwitchboardController::class, 'toggle'])->whereNumber('id');
                Route::post('/{id}/health-check',          [IntegrationSwitchboardController::class, 'healthCheck'])->whereNumber('id');
                Route::post('/health-check-all',           [IntegrationSwitchboardController::class, 'healthCheckAll']);
                Route::put('/category/{category}/primary', [IntegrationSwitchboardController::class, 'setPrimary']);
            });

            // Screen 54 — Feature Flags & A/B Tests
            Route::prefix('feature-flags')->group(function () {
                Route::get('/',                        [FeatureFlagController::class, 'index']);
                Route::post('/',                       [FeatureFlagController::class, 'store']);
                Route::get('/{key}',                   [FeatureFlagController::class, 'show']);
                Route::put('/{key}',                   [FeatureFlagController::class, 'update']);
                Route::post('/{key}/kill',             [FeatureFlagController::class, 'kill']);
                Route::post('/{key}/ab-test',          [FeatureFlagController::class, 'createAbTest']);
                Route::get('/{key}/ab-test/results',   [FeatureFlagController::class, 'abTestResults']);
                Route::get('/{key}/audit',             [FeatureFlagController::class, 'audit']);
            });

            // Screen 55 — System Parameters & Settings
            Route::prefix('system')->group(function () {
                Route::get('/parameters',                  [SystemParameterController::class, 'index']);
                Route::get('/parameters/audit',            [SystemParameterController::class, 'audit']);
                Route::get('/parameters/debug-logging',    [SystemParameterController::class, 'debugLoggingStatus']);
                Route::put('/parameters/debug-logging',    [SystemParameterController::class, 'toggleDebugLogging']);
                Route::post('/parameters/reset',          [SystemParameterController::class, 'resetToDefaults']);
                Route::get('/parameters/{key}',            [SystemParameterController::class, 'show']);
                Route::put('/parameters',                  [SystemParameterController::class, 'update']);
                Route::post('/maintenance',                [SystemParameterController::class, 'toggleMaintenance']);
            });

            // ─────────────────────────────────────────────────────────────────────
            // PHASE 14 — Support & Helpdesk
            // Screens 56 (Master Ticket Queue), 57 (Ticket Detail & SLA Tracking)
            // ─────────────────────────────────────────────────────────────────────

            Route::prefix('tickets')->group(function () {
                Route::get('/stats',                  [TicketController::class, 'stats']);
                Route::post('/bulk',                  [TicketController::class, 'bulk']);
                Route::get('/',                       [TicketController::class, 'index']);
                Route::post('/',                      [TicketController::class, 'store']);
                Route::get('/{id}',                   [TicketController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',                   [TicketController::class, 'update'])->whereNumber('id');
                Route::get('/{id}/sla',               [TicketController::class, 'sla'])->whereNumber('id');
                Route::post('/{id}/messages',         [TicketController::class, 'addMessage'])->whereNumber('id');
                Route::post('/{id}/escalate',         [TicketController::class, 'escalate'])->whereNumber('id');
                Route::post('/{id}/reassign',         [TicketController::class, 'reassign'])->whereNumber('id');
                Route::post('/{id}/resolve',          [TicketController::class, 'resolve'])->whereNumber('id');
            });

        }); // end admin middleware group
});
