<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\ResendNotificationJob;

/**
 * Phase 12 — Screen 50: Communication Logs
 * Every notification sent — delivery status, click tracking, provider detail, resend
 */
class CommunicationLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:notifications.logs.view')
            ->only(['index', 'show', 'summary', 'dailyTrend']);

        $this->middleware('permission:notifications.logs.resend')
            ->only(['resend']);
    }

    /**
     * GET /api/admin/communication-logs
     * Paginated log with multi-filter support
     */
    public function index(Request $request)
    {
        $request->validate([
            'channel'      => 'nullable|in:sms,email,whatsapp,push',
            'status'       => 'nullable|in:sent,delivered,read,clicked,failed,bounced',
            'template_key' => 'nullable|string|max:100',
            'recipient'    => 'nullable|string|max:200',
            'provider'     => 'nullable|in:msg91,ses,meta_wa,firebase',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'merchant_id'  => 'nullable|integer|exists:merchants,id',
            'per_page'     => 'nullable|integer|min:10|max:100',
        ]);

        $logs = DB::table('communication_logs as cl')
            ->leftJoin('notification_templates as nt', 'cl.template_key', '=', 'nt.template_key')
            ->leftJoin('merchants as m', 'cl.merchant_id', '=', 'm.id')
            ->select(
                'cl.id', 'cl.channel', 'cl.recipient', 'cl.status',
                'cl.provider', 'cl.provider_message_id',
                'cl.sent_at', 'cl.delivered_at', 'cl.read_at', 'cl.clicked_at', 'cl.failed_at',
                'cl.failure_reason', 'cl.cost',
                'cl.template_key', 'nt.name as template_name',
                'cl.merchant_id', 'm.business_name as merchant_name',
                'cl.entity_type', 'cl.entity_id'
            )
            ->when($request->channel,      fn($q) => $q->where('cl.channel', $request->channel))
            ->when($request->status,       fn($q) => $q->where('cl.status', $request->status))
            ->when($request->template_key, fn($q) => $q->where('cl.template_key', $request->template_key))
            ->when($request->recipient,    fn($q) => $q->where('cl.recipient', 'LIKE', "%{$request->recipient}%"))
            ->when($request->provider,     fn($q) => $q->where('cl.provider', $request->provider))
            ->when($request->merchant_id,  fn($q) => $q->where('cl.merchant_id', $request->merchant_id))
            ->when($request->start_date,   fn($q) => $q->whereDate('cl.sent_at', '>=', $request->start_date))
            ->when($request->end_date,     fn($q) => $q->whereDate('cl.sent_at', '<=', $request->end_date))
            ->orderByDesc('cl.sent_at')
            ->paginate($request->per_page ?? 50);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * GET /api/admin/communication-logs/{id}
     * Single log entry with full provider detail
     */
    public function show(int $id)
    {
        $log = DB::table('communication_logs')
            ->leftJoin('notification_templates', 'communication_logs.template_key', '=', 'notification_templates.template_key')
            ->where('communication_logs.id', $id)
            ->select('communication_logs.*', 'notification_templates.name as template_name')
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $log]);
    }

    /**
     * POST /api/admin/communication-logs/resend
     * Bulk resend failed notifications
     */
    public function resend(Request $request)
    {
        $request->validate([
            'log_ids'   => 'required|array|min:1|max:100',
            'log_ids.*' => 'integer|exists:communication_logs,id',
        ]);

        $failedLogs = DB::table('communication_logs')
            ->whereIn('id', $request->log_ids)
            ->where('status', 'failed')
            ->get();

        foreach ($failedLogs as $log) {
            dispatch(new ResendNotificationJob($log->id));
        }

        return response()->json([
            'success' => true,
            'queued'  => $failedLogs->count(),
            'message' => "{$failedLogs->count()} notifications queued for resend.",
        ]);
    }

    /**
     * GET /api/admin/communication-logs/stats/summary
     * Delivery stats summary with cost breakdown per channel
     */
    public function summary(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:7d,30d,90d',
        ]);

        [$start, $end] = $this->resolveDateRange($request->period ?? '30d');

        $stats = DB::table('communication_logs')
            ->whereBetween('sent_at', [$start, $end])
            ->select(
                'channel',
                'provider',
                DB::raw('COUNT(*) as total_sent'),
                DB::raw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered"),
                DB::raw("SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count"),
                DB::raw("SUM(CASE WHEN status = 'clicked' THEN 1 ELSE 0 END) as clicked"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw('SUM(cost) as total_cost'),
                DB::raw('ROUND(AVG(EXTRACT(EPOCH FROM (delivered_at - sent_at)))::numeric, 1) as avg_delivery_seconds')
            )
            ->groupBy('channel', 'provider')
            ->get();

        $totals = DB::table('communication_logs')
            ->whereBetween('sent_at', [$start, $end])
            ->select(
                DB::raw('COUNT(*) as total_sent'),
                DB::raw('SUM(cost) as total_cost'),
                DB::raw('SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as total_failed')
            )
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'by_channel_provider' => $stats,
                'totals'              => $totals,
                'period'              => ['start' => $start, 'end' => $end],
            ],
        ]);
    }

    /**
     * GET /api/admin/communication-logs/stats/daily-trend
     * Daily send volume trend per channel
     */
    public function dailyTrend(Request $request)
    {
        $request->validate(['period' => 'nullable|in:7d,30d,90d']);
        [$start, $end] = $this->resolveDateRange($request->period ?? '30d');

        $trend = DB::table('communication_logs')
            ->whereBetween('sent_at', [$start, $end])
            ->selectRaw("sent_at::date as date, channel, COUNT(*) as count, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->groupBy('date', 'channel')
            ->orderBy('date')
            ->get();

        return response()->json(['success' => true, 'data' => $trend]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function resolveDateRange(string $period): array
    {
        return match ($period) {
            '7d'  => [now()->subDays(7)->toDateTimeString(),  now()->toDateTimeString()],
            '30d' => [now()->subDays(30)->toDateTimeString(), now()->toDateTimeString()],
            '90d' => [now()->subDays(90)->toDateTimeString(), now()->toDateTimeString()],
            default => [now()->subDays(30)->toDateTimeString(), now()->toDateTimeString()],
        };
    }
}