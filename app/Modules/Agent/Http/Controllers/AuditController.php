<?php

namespace App\Modules\Agent\Http\Controllers;

use App\Models\AgentStoreVisit;
use App\Models\Store;
use App\Models\Ticket;
use App\Models\TicketLink;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 Screen 46 — Merchant Store Visit & Audit Logger
 */
class AuditController extends AgentBaseController
{
    public function stores(Request $request)
    {
        $stores = Store::query()
            ->whereHas('merchant', fn ($q) => $q->where('sales_exec_id', $this->scopedSalesExecId()))
            ->with(['merchant:id,business_name,status,onboarding_stage,gst_number'])
            ->get()
            ->map(function (Store $store) {
                $lastVisit = AgentStoreVisit::query()
                    ->where('store_id', $store->id)
                    ->where('sales_exec_id', $this->scopedSalesExecId())
                    ->latest('checked_in_at')
                    ->first();

                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'address' => $store->address,
                    'latitude' => $store->latitude,
                    'longitude' => $store->longitude,
                    'status' => $store->status,
                    'merchant' => $store->merchant,
                    'last_checkin_at' => $lastVisit?->checked_in_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $stores,
        ]);
    }

    public function index(Request $request)
    {
        $visits = AgentStoreVisit::query()
            ->where('sales_exec_id', $this->scopedSalesExecId())
            ->with(['merchant:id,business_name', 'store:id,name,address'])
            ->latest('checked_in_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $visits->items(),
            'meta' => [
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'total' => $visits->total(),
            ],
        ]);
    }

    public function checkin(Request $request)
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'qr_standee_placed' => ['sometimes', 'boolean'],
            'staff_trained' => ['sometimes', 'boolean'],
            'pos_active' => ['sometimes', 'boolean'],
            'merchant_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $store = $this->findAgentStore((int) $data['store_id']);
        $merchant = $store->merchant;

        $visit = AgentStoreVisit::create([
            'sales_exec_id' => $this->scopedSalesExecId(),
            'merchant_id' => $merchant->id,
            'store_id' => $store->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'checked_in_at' => now(),
            'qr_standee_placed' => (bool) ($data['qr_standee_placed'] ?? false),
            'staff_trained' => (bool) ($data['staff_trained'] ?? false),
            'pos_active' => (bool) ($data['pos_active'] ?? false),
            'merchant_active' => (bool) ($data['merchant_active'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);

        $checklistComplete = $visit->qr_standee_placed
            && $visit->staff_trained
            && $visit->pos_active
            && $visit->merchant_active;

        if ($checklistComplete && !in_array($merchant->status, ['Approved', 'Rejected', 'Suspended'], true)) {
            $merchant->onboarding_stage = 'inspection_done';
            $merchant->status = 'Under Review';
            $merchant->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Store visit GPS check-in recorded.',
            'data' => [
                'visit' => $visit->load(['merchant:id,business_name,status,onboarding_stage', 'store:id,name']),
                'checklist_complete' => $checklistComplete,
            ],
        ], 201);
    }

    public function escalate(Request $request, int $id)
    {
        $visit = AgentStoreVisit::query()
            ->where('sales_exec_id', $this->scopedSalesExecId())
            ->where('id', $id)
            ->with('store')
            ->firstOrFail();

        $data = $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
            'priority' => ['sometimes', 'in:critical,high,medium,low'],
        ]);

        $user = auth('api')->user();
        $storeName = $visit->store?->name ?? ('Store #' . $visit->store_id);

        $ticket = DB::transaction(function () use ($visit, $data, $user, $storeName) {
            $year = now()->format('Y');
            $prefix = "TKT-{$year}-";
            $latest = Ticket::query()
                ->where('ticket_number', 'like', $prefix . '%')
                ->orderByDesc('ticket_number')
                ->value('ticket_number');
            $sequence = 1;
            if ($latest && preg_match('/TKT-\d{4}-(\d+)$/', $latest, $matches)) {
                $sequence = (int) $matches[1] + 1;
            }

            $ticket = Ticket::create([
                'ticket_number' => $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
                'subject' => 'Field audit escalation — ' . $storeName,
                'description' => $data['notes'],
                'source_role' => 'internal',
                'category' => 'complaint',
                'priority' => $data['priority'] ?? 'high',
                'status' => 'open',
                'sla_state' => 'ok',
                'reporter_name' => $user?->name ?? 'Sales Executive',
                'reporter_email' => $user?->email,
                'reporter_phone' => $user?->mobile,
                'entity_type' => 'store',
                'entity_id' => $visit->store_id,
                'first_response_due_at' => now()->addHours(24),
                'resolution_due_at' => now()->addHours(72),
                'created_by' => $user?->id,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'visibility' => 'public',
                'author_type' => 'admin',
                'author_id' => $user?->id,
                'author_name' => $user?->name ?? 'Sales Executive',
                'body' => $data['notes'],
            ]);

            TicketLink::create([
                'ticket_id' => $ticket->id,
                'entity_type' => 'store',
                'entity_id' => $visit->store_id,
                'label' => 'Store #' . $visit->store_id,
            ]);

            return $ticket;
        });

        $visit->ticket_id = $ticket->id;
        $visit->notes = trim(($visit->notes ? $visit->notes . "\n" : '') . 'ESCALATED: ' . $data['notes']);
        $visit->save();

        return response()->json([
            'success' => true,
            'message' => 'Issue escalated to Super Admin helpdesk.',
            'data' => [
                'visit_id' => $visit->id,
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ],
        ]);
    }
}
