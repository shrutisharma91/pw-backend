<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| DashboardController
|--------------------------------------------------------------------------
| Screen 06 — Master Dashboard
|
| APIs:
|   GET /api/v1/admin/dashboard            → main KPIs + charts
|   GET /api/v1/admin/dashboard/live-stream → recent loan applications
|   GET /api/v1/admin/dashboard/action-tray → pending approvals, fraud flags
|
| Note: Since merchants/loans/lenders tables don't exist yet,
| these return realistic dummy data for now.
| Replace DB::table() calls with real models as other phases are built.
*/

class DashboardController extends Controller
{
    // ------------------------------------------------------------------
    // GET /api/v1/admin/dashboard
    // Screen 06 — Top KPIs + Revenue Chart + Recent Signups
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        // Period toggle: 7d / 30d / 90d (default 30d)
        $period = $request->get('period', '30d');
        $days   = match($period) {
            '7d'  => 7,
            '90d' => 90,
            default => 30,
        };

        $from = now()->subDays($days);

        // -----------------------------------------------
        // TOP KPIs
        // Replace these with real DB queries as you build
        // other modules (merchants, loans, lenders tables)
        // -----------------------------------------------
        $kpis = [
            'total_merchants'   => DB::table('users')->where('role', 'merchant_admin')->count() ?? 0,
            'active_stores'     => 0,   // replace: Store::where('is_active', true)->count()
            'lenders_live'      => 0,   // replace: Lender::where('status', 'live')->count()
            'todays_disbursals' => 0,   // replace: Loan::whereDate('disbursed_at', today())->sum('amount')
            'total_users'       => User::where('is_active', true)->count(),
            'pending_approvals' => 0,   // replace: Merchant::where('status', 'under_review')->count()
        ];

        // -----------------------------------------------
        // REVENUE TREND CHART DATA
        // Returns array of {date, disbursals, revenue}
        // -----------------------------------------------
        $chartData = $this->buildChartData($days);

        // -----------------------------------------------
        // RECENT MERCHANT SIGNUPS
        // -----------------------------------------------
        $recentMerchants = User::where('role', 'merchant_admin')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'email', 'created_at', 'is_active']);

        return response()->json([
            'success'          => true,
            'period'           => $period,
            'kpis'             => $kpis,
            'chart_data'       => $chartData,
            'recent_merchants' => $recentMerchants,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/dashboard/live-stream
    // Recent loan applications across all merchants
    // ------------------------------------------------------------------
    public function liveStream()
    {
        // Replace with real Loan model when Phase 2 backend is built
        // Loan::with(['merchant', 'customer'])->latest()->limit(10)->get()

        return response()->json([
            'success' => true,
            'data'    => [], // will be populated when loan tables exist
            'message' => 'Loan tables not yet created. Will populate in Phase 2.',
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/dashboard/action-tray
    // Pending approvals, SLA breaches, fraud flags
    // ------------------------------------------------------------------
    public function actionTray()
    {
        $pendingApprovals = User::where('role', 'merchant_admin')
            ->where('is_active', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pending_merchant_approvals' => $pendingApprovals,
                'sla_breaches'               => 0,  // replace when loan tables exist
                'fraud_flags'                => 0,  // replace when risk tables exist
                'pending_offers'             => 0,  // replace when offer tables exist
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Private: Build chart data for the trend chart
    // ------------------------------------------------------------------
    private function buildChartData(int $days): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date   = now()->subDays($i)->format('Y-m-d');
            $data[] = [
                'date'        => $date,
                'disbursals'  => 0, // replace: Loan::whereDate('disbursed_at', $date)->sum('amount')
                'revenue'     => 0, // replace: real revenue calculation
                'applications'=> 0, // replace: Loan::whereDate('created_at', $date)->count()
            ];
        }
        return $data;
    }
}