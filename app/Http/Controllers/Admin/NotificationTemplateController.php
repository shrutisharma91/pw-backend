<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 12 — Screen 49: Notification Template Manager
 * SMS / Email / WhatsApp / Push templates with variables, versioning, DLT compliance
 */
class NotificationTemplateController extends Controller
{
    public function __construct(private NotificationService $notificationService)
    {
        $this->middleware('permission:notifications.templates.view')
            ->only(['index', 'show', 'diff', 'availableVariables']);

        $this->middleware('permission:notifications.templates.create')
            ->only(['store']);

        $this->middleware('permission:notifications.templates.edit')
            ->only(['update', 'rollback']);

        $this->middleware('permission:notifications.templates.approve')
            ->only(['activate']);
    }

    /**
     * GET /api/admin/templates
     * List templates with filter by channel / status
     */
    public function index(Request $request)
    {
        $request->validate([
            'channel' => 'nullable|in:sms,email,whatsapp,push',
            'status'  => 'nullable|in:draft,active,archived',
            'search'  => 'nullable|string|max:200',
        ]);

        $templates = NotificationTemplate::query()
            ->when($request->channel, fn($q) => $q->where('channel', $request->channel))
            ->when($request->status,  fn($q) => $q->where('status', $request->status))
            ->when($request->search,  fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'LIKE', "%{$request->search}%")
                   ->orWhere('template_key', 'LIKE', "%{$request->search}%");
            }))
            ->withCount('versions')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $templates]);
    }

    /**
     * GET /api/admin/templates/{id}
     * Single template with current version content
     */
    public function show(int $id)
    {
        $template = NotificationTemplate::with(['currentVersion', 'versions' => fn($q) => $q->orderByDesc('version_number')->limit(5)])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $template]);
    }

    /**
     * POST /api/admin/templates
     * Create a new template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'template_key'  => 'required|string|max:100|unique:notification_templates,template_key',
            'channel'       => 'required|in:sms,email,whatsapp,push',
            'subject'       => 'nullable|required_if:channel,email|string|max:255',
            'body'          => 'required|string',
            'variables'     => 'nullable|array',                   // e.g. ['customer_name', 'loan_amount']
            'variables.*'   => 'string|max:100',
            'sender_id'     => 'nullable|string|max:50',           // DLT sender ID for SMS
            'dlt_template_id' => 'nullable|string|max:100',        // DLT template ID
            'language'      => 'nullable|string|max:10',
        ]);

        $template = DB::transaction(function () use ($validated) {
            $tmpl = NotificationTemplate::create([
                'name'           => $validated['name'],
                'template_key'   => $validated['template_key'],
                'channel'        => $validated['channel'],
                'subject'        => $validated['subject'] ?? null,
                'variables'      => json_encode($validated['variables'] ?? []),
                'sender_id'      => $validated['sender_id'] ?? null,
                'dlt_template_id'=> $validated['dlt_template_id'] ?? null,
                'language'       => $validated['language'] ?? 'en',
                'status'         => 'draft',
                'created_by'     => auth()->id(),
            ]);

            // Create initial version
            $tmpl->versions()->create([
                'version_number' => 1,
                'body'           => $validated['body'],
                'subject'        => $validated['subject'] ?? null,
                'is_active'      => true,
                'created_by'     => auth()->id(),
            ]);

            $tmpl->update(['current_version' => 1]);

            return $tmpl;
        });

        activity()->log("Notification template created: {$template->name} ({$template->channel})");

        return response()->json(['success' => true, 'data' => $template, 'message' => 'Template created.'], 201);
    }

    /**
     * PUT /api/admin/templates/{id}
     * Update template — creates a new version, keeps old versions
     */
    public function update(Request $request, int $id)
    {
        $template = NotificationTemplate::findOrFail($id);

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'subject'        => 'nullable|string|max:255',
            'body'           => 'required|string',
            'variables'      => 'nullable|array',
            'sender_id'      => 'nullable|string|max:50',
            'dlt_template_id'=> 'nullable|string|max:100',
            'language'       => 'nullable|string|max:10',
        ]);

        DB::transaction(function () use ($template, $validated) {
            // Archive current active version
            $template->versions()->where('is_active', true)->update(['is_active' => false]);

            $newVersionNumber = ($template->current_version ?? 0) + 1;

            $template->versions()->create([
                'version_number' => $newVersionNumber,
                'body'           => $validated['body'],
                'subject'        => $validated['subject'] ?? $template->subject,
                'is_active'      => true,
                'created_by'     => auth()->id(),
            ]);

            $template->update(array_merge(
                array_filter($validated, fn($v) => $v !== null && !in_array($v, ['body'])),
                ['current_version' => $newVersionNumber, 'status' => 'draft']
            ));
        });

        activity()->log("Template updated: {$template->name} → v{$template->current_version}");

        return response()->json(['success' => true, 'message' => "Template updated to v{$template->current_version}."]);
    }

    /**
     * POST /api/admin/templates/{id}/activate
     * Activate a template (moves from draft to active)
     */
    public function activate(int $id)
    {
        $template = NotificationTemplate::findOrFail($id);
        $template->update(['status' => 'active', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        activity()->log("Template activated: {$template->name}");

        return response()->json(['success' => true, 'message' => 'Template is now active.']);
    }

    /**
     * POST /api/admin/templates/{id}/archive
     * Soft archive a template
     */
    public function archive(int $id)
    {
        $template = NotificationTemplate::findOrFail($id);
        $template->update(['status' => 'archived']);

        return response()->json(['success' => true, 'message' => 'Template archived.']);
    }

    /**
     * POST /api/admin/templates/{id}/rollback/{version}
     * Roll back to a previous version
     */
    public function rollback(int $id, int $version)
    {
        $template      = NotificationTemplate::findOrFail($id);
        $targetVersion = $template->versions()->where('version_number', $version)->firstOrFail();

        DB::transaction(function () use ($template, $targetVersion) {
            $template->versions()->where('is_active', true)->update(['is_active' => false]);
            $targetVersion->update(['is_active' => true]);
            $template->update(['current_version' => $targetVersion->version_number, 'status' => 'draft']);
        });

        activity()->log("Template rolled back: {$template->name} → v{$version}");

        return response()->json(['success' => true, 'message' => "Rolled back to version {$version}."]);
    }

    /**
     * GET /api/admin/templates/{id}/diff/{v1}/{v2}
     * Version diff viewer
     */
    public function diff(int $id, int $v1, int $v2)
    {
        $template  = NotificationTemplate::findOrFail($id);
        $version1  = $template->versions()->where('version_number', $v1)->firstOrFail();
        $version2  = $template->versions()->where('version_number', $v2)->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => [
                'v1' => ['version' => $v1, 'body' => $version1->body, 'subject' => $version1->subject, 'created_at' => $version1->created_at],
                'v2' => ['version' => $v2, 'body' => $version2->body, 'subject' => $version2->subject, 'created_at' => $version2->created_at],
            ],
        ]);
    }

    /**
     * POST /api/admin/templates/{id}/test-send
     * Send a test notification before going live
     */
    public function testSend(Request $request, int $id)
    {
        $request->validate([
            'to'        => 'required|string',
            'variables' => 'nullable|array',
        ]);

        $template = NotificationTemplate::findOrFail($id);
        $body     = $this->interpolate($template->activeVersion->body, $request->variables ?? []);

        $result   = $this->notificationService->sendTest($template->channel, $request->to, $body, $template->subject);

        $logId = DB::table('communication_logs')->insertGetId([
            'channel'              => $template->channel,
            'recipient'            => $request->to,
            'template_key'         => $template->template_key,
            'provider'             => match ($template->channel) {
                'sms'      => 'msg91',
                'email'    => 'ses',
                'whatsapp' => 'meta_wa',
                'push'     => 'firebase',
                default    => 'msg91',
            },
            'status'               => $result ? 'sent' : 'failed',
            'failure_reason'       => $result ? null : 'Provider dispatch failed or not configured.',
            'sent_at'              => now(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        activity()->log("Template test send: {$template->name} → {$request->to}");

        return response()->json([
            'success' => true,
            'message' => 'Test notification dispatched.',
            'preview' => $body,
            'log_id'  => $logId,
        ]);
    }

    /**
     * GET /api/admin/templates/variables
     * Available system variables for the template editor
     */
    public function availableVariables()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'customer'   => ['customer_name', 'customer_mobile', 'customer_email'],
                'loan'       => ['loan_id', 'loan_amount', 'emi_amount', 'tenure_months', 'due_date'],
                'merchant'   => ['merchant_name', 'store_name', 'city'],
                'platform'   => ['platform_name', 'support_email', 'support_number'],
            ],
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function interpolate(string $body, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $body = str_replace("{{$key}}", $value, $body);
        }
        return $body;
    }
}