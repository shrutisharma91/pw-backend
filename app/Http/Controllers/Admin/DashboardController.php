<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Disbursal;
use App\Models\FraudAlert;
use App\Models\Lender;
use App\Models\LoanApplication;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
*/

class DashboardController extends Controller
{
  public function index(Request $request)
  {
    $period = $request->get('period', '30d');
    $days   = match ($period) {
      '7d'  => 7,
      '90d' => 90,
      default => 30,
    };

    $kpis = [
      'total_merchants'   => Merchant::count(),
      'active_stores'     => Store::where('status', 'active')->count(),
      'lenders_live'      => Lender::where('api_status', 'live')->count(),
      'todays_disbursals' => (float) Disbursal::whereDate('created_at', today())
        ->where('status', 'Success')
        ->sum('amount'),
      'total_users'       => User::where('is_active', true)->count(),
      'pending_approvals' => Merchant::whereIn('status', ['Submitted', 'Under Review'])->count(),
    ];

    $chartData = $this->buildChartData($days);

    $labels = array_map(fn ($row) => substr($row['date'], 5), $chartData);
    $disbursalSeries = array_map(fn ($row) => round(((float) $row['disbursals']) / 100000, 2), $chartData);
    $revenueSeries = array_map(fn ($row) => round(((float) $row['revenue']) / 100000, 2), $chartData);

    $recentSignups = Merchant::query()
      ->orderByDesc('created_at')
      ->limit(5)
      ->get(['id', 'business_name', 'region', 'tier', 'status', 'created_at'])
      ->map(fn (Merchant $m) => [
        'id'         => $m->id,
        'name'       => $m->business_name,
        'merchant_name' => $m->business_name,
        'region'     => $m->region ?? '—',
        'plan'       => $m->tier ?? $m->status ?? '—',
        'time'       => $m->created_at?->toIso8601String(),
        'created_at' => $m->created_at?->toIso8601String(),
      ])
      ->values();

    $funnel = LoanApplication::query()
      ->select('status', DB::raw('COUNT(*) as count'))
      ->groupBy('status')
      ->orderByDesc('count')
      ->get()
      ->map(fn ($row) => [
        'label' => $row->status,
        'stage' => $row->status,
        'value' => (int) $row->count,
        'count' => (int) $row->count,
      ])
      ->values();

    return response()->json([
      'success'          => true,
      'period'           => $period,
      'kpis'             => $kpis,
      'chart_data'       => $chartData,
      'trend'            => [
        'labels'    => $labels,
        'disbursal' => $disbursalSeries,
        'values'    => $disbursalSeries,
      ],
      'revenue_trend'    => [
        'labels' => $labels,
        'values' => $revenueSeries,
      ],
      'recent_signups'   => $recentSignups,
      'recent_merchants' => $recentSignups,
      'funnel'           => $funnel,
      'stage_pipeline'   => $funnel,
    ]);
  }

  public function liveStream()
  {
    $applications = LoanApplication::query()
      ->with(['merchant:id,business_name', 'store:id,name'])
      ->latest()
      ->limit(10)
      ->get()
      ->map(fn (LoanApplication $loan) => [
        'id'             => 'LA-' . str_pad((string) $loan->id, 4, '0', STR_PAD_LEFT),
        'application_id' => $loan->id,
        'merchant'       => $loan->merchant?->business_name ?? '—',
        'merchant_name'  => $loan->merchant?->business_name ?? '—',
        'amount'         => '₹' . number_format((float) $loan->amount, 0),
        'stage'          => $loan->status,
        'status'         => $loan->status,
        'time'           => $loan->created_at?->diffForHumans(),
        'created_at'     => $loan->created_at?->toIso8601String(),
      ])
      ->values();

    return response()->json([
      'success' => true,
      'data'    => $applications,
      'items'   => $applications,
    ]);
  }

  public function actionTray()
  {
    $pendingMerchants = Merchant::whereIn('status', ['Submitted', 'Under Review'])->count();
    $slaBreaches      = LoanApplication::where('sla_breached', true)->count();
    $fraudFlags       = FraudAlert::where('status', 'Open')->count();
    $pendingOffers    = Offer::where('status', 'Pending')->count();

    $items = [];

    if ($pendingMerchants > 0) {
      $items[] = [
        'type'     => 'merchant',
        'label'    => "{$pendingMerchants} merchant(s) pending approval",
        'severity' => 'warning',
        'link'     => '/merchants',
      ];
    }

    if ($slaBreaches > 0) {
      $items[] = [
        'type'     => 'sla',
        'label'    => "{$slaBreaches} loan application(s) breached SLA",
        'severity' => 'danger',
        'link'     => '/loan-application-monitor',
      ];
    }

    if ($fraudFlags > 0) {
      $items[] = [
        'type'     => 'fraud',
        'label'    => "{$fraudFlags} open fraud flag(s)",
        'severity' => 'danger',
        'link'     => '/fraud-alert-feed',
      ];
    }

    if ($pendingOffers > 0) {
      $items[] = [
        'type'     => 'offer',
        'label'    => "{$pendingOffers} offer(s) awaiting approval",
        'severity' => 'info',
        'link'     => '/pricing/offers/approval',
      ];
    }

    return response()->json([
      'success' => true,
      'items'   => $items,
      'data'    => [
        'pending_merchant_approvals' => $pendingMerchants,
        'sla_breaches'               => $slaBreaches,
        'fraud_flags'                => $fraudFlags,
        'pending_offers'             => $pendingOffers,
      ],
    ]);
  }

  private function buildChartData(int $days): array
  {
    $data = [];

    for ($i = $days - 1; $i >= 0; $i--) {
      $date = now()->subDays($i);
      $dateStr = $date->format('Y-m-d');

      $disbursalAmount = (float) Disbursal::query()
        ->where('status', 'Success')
        ->whereDate('created_at', $dateStr)
        ->sum('amount');

      $applicationCount = LoanApplication::query()
        ->whereDate('created_at', $dateStr)
        ->count();

      $data[] = [
        'date'         => $dateStr,
        'disbursals'   => $disbursalAmount,
        'revenue'      => round($disbursalAmount * 0.02, 2),
        'applications' => $applicationCount,
      ];
    }

    return $data;
  }
}
