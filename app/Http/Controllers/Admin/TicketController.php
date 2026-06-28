<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\ReassignTicketRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 14 — Screens 56 & 57: Master Ticket Queue & Ticket Detail / SLA
 */
class TicketController extends Controller
{
    public function __construct(private TicketService $ticketService)
    {
        $this->middleware('permission:support.tickets.view')
            ->only(['index', 'show', 'stats', 'sla']);

        $this->middleware('permission:support.tickets.create')
            ->only(['store']);

        $this->middleware('permission:support.tickets.edit')
            ->only(['update']);

        $this->middleware('permission:support.tickets.respond')
            ->only(['addMessage', 'resolve']);

        $this->middleware('permission:support.tickets.escalate')
            ->only(['escalate']);

        $this->middleware('permission:support.tickets.reassign')
            ->only(['reassign']);

        $this->middleware('permission:support.tickets.bulk')
            ->only(['bulk']);
    }

    /**
     * GET /api/admin/tickets
     * Master ticket queue with filters
     */
    public function index(Request $request)
    {
        $request->validate([
            'source_role' => 'nullable|in:merchant,customer,store,lender_ops,internal',
            'category'    => 'nullable|in:dispute,complaint,technical,billing,kyc,loan,settlement,agreement,other',
            'priority'    => 'nullable|in:critical,high,medium,low',
            'status'      => 'nullable|in:open,in_progress,waiting,resolved,closed,escalated',
            'sla_state'   => 'nullable|in:ok,at_risk,breached',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'search'      => 'nullable|string|max:200',
            'entity_type' => 'nullable|string|max:50',
            'entity_id'   => 'nullable|integer',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date',
            'per_page'    => 'nullable|integer|min:10|max:100',
        ]);

        $tickets = Ticket::query()
            ->with(['assignee:id,name,email'])
            ->when($request->source_role, fn ($q) => $q->where('source_role', $request->source_role))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->sla_state, fn ($q) => $q->where('sla_state', $request->sla_state))
            ->when($request->assigned_to, fn ($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->entity_type, fn ($q) => $q->where('entity_type', $request->entity_type))
            ->when($request->entity_id, fn ($q) => $q->where('entity_id', $request->entity_id))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($inner) use ($request) {
                    $inner->where('ticket_number', 'ILIKE', "%{$request->search}%")
                        ->orWhere('subject', 'ILIKE', "%{$request->search}%")
                        ->orWhere('reporter_name', 'ILIKE', "%{$request->search}%");
                });
            })
            ->when($request->start_date, fn ($q) => $q->whereDate('created_at', '>=', $request->start_date))
            ->when($request->end_date, fn ($q) => $q->whereDate('created_at', '<=', $request->end_date))
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 25);

        $tickets->getCollection()->transform(function (Ticket $ticket) {
            $ticket->sla = $this->slaPayload($ticket);

            return $ticket;
        });

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    /**
     * POST /api/admin/tickets
     * Create a new support ticket
     */
    public function store(StoreTicketRequest $request)
    {
        $ticket = $this->ticketService->create(
            $request->validatedPayload(),
            $request->user(),
            array_filter($request->file('attachments', []) ?? [])
        );

        $ticket->load('creator:id,name,email');
        $ticket->sla = $this->slaPayload($ticket);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            'data'    => new TicketResource($ticket),
        ], 201);
    }

    /**
     * GET /api/admin/tickets/stats
     * Queue summary for dashboard widgets
     */
    public function stats()
    {
        $byStatus = Ticket::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $bySla = Ticket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->select('sla_state', DB::raw('COUNT(*) as count'))
            ->groupBy('sla_state')
            ->pluck('count', 'sla_state');

        $byPriority = Ticket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return response()->json([
            'success' => true,
            'data'    => [
                'total_open'    => Ticket::whereNotIn('status', ['resolved', 'closed'])->count(),
                'by_status'     => $byStatus,
                'by_sla_state'  => $bySla,
                'by_priority'   => $byPriority,
                'breached'      => (int) ($bySla['breached'] ?? 0),
                'unassigned'    => Ticket::whereNull('assigned_to')->whereNotIn('status', ['resolved', 'closed'])->count(),
            ],
        ]);
    }

    /**
     * POST /api/admin/tickets/bulk
     * Bulk reassign, close, or escalate
     */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'action'       => 'required|in:reassign,close,escalate',
            'ticket_ids'   => 'required|array|min:1',
            'ticket_ids.*' => 'integer|exists:tickets,id',
            'assignee_id'  => 'required_if:action,reassign|nullable|integer|exists:users,id',
            'escalate_to'  => 'required_if:action,escalate|nullable|integer|exists:users,id',
            'note'         => 'nullable|string|max:500',
        ]);

        $updated = 0;

        DB::transaction(function () use ($validated, &$updated) {
            $tickets = Ticket::whereIn('id', $validated['ticket_ids'])->get();

            foreach ($tickets as $ticket) {
                match ($validated['action']) {
                    'reassign' => $ticket->update([
                        'assigned_to' => $validated['assignee_id'],
                        'status'      => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
                    ]),
                    'close' => $ticket->update([
                        'status'     => 'closed',
                        'closed_at'  => now(),
                        'sla_state'  => $this->computeSlaState($ticket->fresh()),
                    ]),
                    'escalate' => $ticket->update([
                        'status'       => 'escalated',
                        'escalated_at' => now(),
                        'escalated_to' => $validated['escalate_to'],
                        'priority'     => $ticket->priority === 'critical' ? 'critical' : 'high',
                    ]),
                };

                if (! empty($validated['note'])) {
                    $this->recordMessage($ticket, $validated['note'], 'internal', 'system');
                }

                $ticket->update(['sla_state' => $this->computeSlaState($ticket->fresh())]);
                $updated++;
            }
        });

        activity()->log("Bulk ticket action '{$validated['action']}' on {$updated} ticket(s)");

        return response()->json([
            'success' => true,
            'message' => "{$updated} ticket(s) updated.",
            'count'   => $updated,
        ]);
    }

    /**
     * GET /api/admin/tickets/{id}
     * Ticket detail with thread, links, and SLA
     */
    public function show(int $id)
    {
        $ticket = Ticket::with([
            'assignee:id,name,email',
            'escalatedToUser:id,name,email',
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'attachments',
            'links',
        ])->findOrFail($id);

        $ticket->sla = $this->slaPayload($ticket);

        return response()->json(['success' => true, 'data' => $ticket]);
    }

    /**
     * PUT /api/admin/tickets/{id}
     * Update assignment, status, priority, category
     */
    public function update(Request $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validated = $request->validate([
            'status'      => 'sometimes|in:open,in_progress,waiting,resolved,closed,escalated',
            'priority'    => 'sometimes|in:critical,high,medium,low',
            'category'    => 'sometimes|in:dispute,complaint,technical,billing,kyc,loan,settlement,agreement,other',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        if (isset($validated['status'])) {
            if ($validated['status'] === 'resolved') {
                $validated['resolved_at'] = now();
            }
            if ($validated['status'] === 'closed') {
                $validated['closed_at'] = now();
            }
        }

        $ticket->update($validated);
        $ticket->update(['sla_state' => $this->computeSlaState($ticket->fresh())]);

        activity()->log("Ticket {$ticket->ticket_number} updated");

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated.',
            'data'    => $ticket->fresh(['assignee:id,name,email']),
        ]);
    }

    /**
     * POST /api/admin/tickets/{id}/messages
     * Add a public reply or internal note to the thread
     */
    public function addMessage(Request $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validated = $request->validate([
            'body'       => 'required|string|max:5000',
            'visibility' => 'required|in:public,internal',
        ]);

        $message = $this->recordMessage(
            $ticket,
            $validated['body'],
            $validated['visibility'],
            'admin',
            auth()->id(),
            auth()->user()?->name ?? 'Super Admin'
        );

        if (! $ticket->first_responded_at && $validated['visibility'] === 'public') {
            $ticket->update(['first_responded_at' => now(), 'status' => 'in_progress']);
        }

        $ticket->update(['sla_state' => $this->computeSlaState($ticket->fresh())]);

        return response()->json([
            'success' => true,
            'message' => 'Message added.',
            'data'    => $message,
        ], 201);
    }

    /**
     * POST /api/admin/tickets/{id}/escalate
     * Escalate ticket to another admin
     */
    public function escalate(Request $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validated = $request->validate([
            'escalate_to' => 'required|integer|exists:users,id',
            'reason'      => 'required|string|max:500',
        ]);

        $ticket->update([
            'status'       => 'escalated',
            'escalated_at' => now(),
            'escalated_to' => $validated['escalate_to'],
            'assigned_to'  => $validated['escalate_to'],
            'priority'     => in_array($ticket->priority, ['critical', 'high'], true) ? $ticket->priority : 'high',
        ]);

        $this->recordMessage($ticket, "Escalated: {$validated['reason']}", 'internal', 'system');

        activity()->log("Ticket {$ticket->ticket_number} escalated to user #{$validated['escalate_to']}");

        return response()->json([
            'success' => true,
            'message' => 'Ticket escalated.',
            'data'    => $ticket->fresh(['escalatedToUser:id,name,email']),
        ]);
    }

    /**
     * POST /api/admin/tickets/{id}/reassign
     * Reassign ticket ownership to another admin (Screen 57 action)
     */
    public function reassign(ReassignTicketRequest $request, int $id)
    {
        $ticket = Ticket::with('assignee:id,name,email')->findOrFail($id);

        $newAssignee = User::findOrFail($request->integer('assignee_id'));

        $ticket = $this->ticketService->reassign(
            $ticket,
            $newAssignee,
            $request->user(),
            $request->input('note')
        );

        $ticket->sla = $this->slaPayload($ticket);

        return response()->json([
            'success' => true,
            'message' => 'Ticket reassigned successfully.',
            'data'    => new TicketResource($ticket),
        ]);
    }

    /**
     * POST /api/admin/tickets/{id}/resolve
     * Resolve with category tagging and optional CSAT trigger
     */
    public function resolve(Request $request, int $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validated = $request->validate([
            'resolution_category' => 'required|in:dispute,complaint,technical,billing,kyc,loan,settlement,agreement,other',
            'resolution_note'     => 'required|string|max:2000',
            'trigger_csat'        => 'nullable|boolean',
            'csat_score'          => 'nullable|integer|min:1|max:5',
            'csat_comment'        => 'nullable|string|max:1000',
        ]);

        $ticket->update([
            'status'              => 'resolved',
            'resolved_at'         => now(),
            'resolution_category' => $validated['resolution_category'],
            'resolution_note'     => $validated['resolution_note'],
            'csat_requested_at'   => $request->boolean('trigger_csat') ? now() : null,
            'csat_score'          => $validated['csat_score'] ?? null,
            'csat_comment'        => $validated['csat_comment'] ?? null,
            'sla_state'           => 'ok',
        ]);

        $this->recordMessage($ticket, $validated['resolution_note'], 'public', 'admin');

        activity()->log("Ticket {$ticket->ticket_number} resolved as {$validated['resolution_category']}");

        return response()->json([
            'success' => true,
            'message' => $request->boolean('trigger_csat')
                ? 'Ticket resolved. CSAT survey triggered.'
                : 'Ticket resolved.',
            'data'    => $ticket->fresh(),
        ]);
    }

    /**
     * GET /api/admin/tickets/{id}/sla
     * SLA countdown and breach prediction
     */
    public function sla(int $id)
    {
        $ticket = Ticket::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->slaPayload($ticket),
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function recordMessage(
        Ticket $ticket,
        string $body,
        string $visibility,
        string $authorType,
        ?int $authorId = null,
        ?string $authorName = null,
    ): TicketMessage {
        return $ticket->messages()->create([
            'visibility'  => $visibility,
            'author_type' => $authorType,
            'author_id'   => $authorId,
            'author_name' => $authorName ?? 'System',
            'body'        => $body,
        ]);
    }

    private function slaPayload(Ticket $ticket): array
    {
        $slaState = $this->computeSlaState($ticket);

        if ($ticket->sla_state !== $slaState) {
            $ticket->update(['sla_state' => $slaState]);
        }

        $now = now();

        return [
            'state'                    => $slaState,
            'first_response_due_at'      => $ticket->first_response_due_at?->toISOString(),
            'resolution_due_at'        => $ticket->resolution_due_at?->toISOString(),
            'first_responded_at'       => $ticket->first_responded_at?->toISOString(),
            'first_response_remaining' => $ticket->first_response_due_at && $now->lessThan($ticket->first_response_due_at)
                ? $now->diffInMinutes($ticket->first_response_due_at)
                : 0,
            'resolution_remaining'     => $ticket->resolution_due_at && $now->lessThan($ticket->resolution_due_at)
                ? $now->diffInMinutes($ticket->resolution_due_at)
                : 0,
            'breach_predicted'         => $slaState === 'at_risk',
            'is_breached'              => $slaState === 'breached',
        ];
    }

    private function computeSlaState(Ticket $ticket): string
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return 'ok';
        }

        $now = now();

        if ($ticket->resolution_due_at && $now->greaterThan($ticket->resolution_due_at)) {
            return 'breached';
        }

        if ($ticket->first_response_due_at && ! $ticket->first_responded_at && $now->greaterThan($ticket->first_response_due_at)) {
            return 'breached';
        }

        $dueAt = $ticket->first_responded_at ? $ticket->resolution_due_at : $ticket->first_response_due_at;

        if ($dueAt && $now->lessThan($dueAt)) {
            $totalMinutes = max(1, $ticket->created_at->diffInMinutes($dueAt));
            $remaining    = $now->diffInMinutes($dueAt);

            if ($remaining <= ($totalMinutes * 0.25)) {
                return 'at_risk';
            }
        }

        return 'ok';
    }
}
