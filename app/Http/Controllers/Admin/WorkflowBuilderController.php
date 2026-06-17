<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 13 — Screen 52: Workflow Builder
 * Visual drag-and-drop approval chains, escalations, routing logic with versioning
 */
class WorkflowBuilderController extends Controller
{
    // Pre-built workflow templates
    private const BUILT_IN_WORKFLOWS = [
        'merchant_onboarding'  => 'Merchant Onboarding',
        'offer_approval'       => 'Offer Approval',
        'manual_override'      => 'Manual Override Approval',
        'loan_exception'       => 'Loan Exception Handling',
        'rekyc_trigger'        => 'Re-KYC Trigger',
        'suspension_approval'  => 'Merchant Suspension',
    ];

    // Allowed node types in canvas
    private const NODE_TYPES = ['start', 'decision', 'action', 'notification', 'escalation', 'parallel', 'end'];

    public function __construct()
    {
        $this->middleware('permission:system.workflows.view')
            ->only(['index', 'show', 'versions', 'archive', 'templates']);

        $this->middleware('permission:system.workflows.create')
            ->only(['store']);

        $this->middleware('permission:system.workflows.edit')
            ->only(['update', 'rollback']);

        $this->middleware('permission:system.workflows.publish')
            ->only(['publish']);
    }

    /**
     * GET /api/admin/workflows
     * List all workflows with status and version
     */
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:draft,published,archived',
            'type'   => 'nullable|in:' . implode(',', array_keys(self::BUILT_IN_WORKFLOWS)),
        ]);

        $workflows = Workflow::query()
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type,   fn($q) => $q->where('workflow_type', $request->type))
            ->withCount('versions')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $workflows]);
    }

    /**
     * GET /api/admin/workflows/{id}
     * Single workflow with active canvas definition
     */
    public function show(int $id)
    {
        $workflow = Workflow::with('activeVersion')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $workflow]);
    }

    /**
     * POST /api/admin/workflows
     * Create a new workflow (from scratch or from built-in template)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'workflow_type'  => 'required|string|max:100',
            'description'    => 'nullable|string|max:500',
            'canvas'         => 'required|array',           // nodes and edges from frontend
            'canvas.nodes'   => 'required|array',
            'canvas.edges'   => 'required|array',
        ]);

        $this->validateCanvas($validated['canvas']);

        $workflow = DB::transaction(function () use ($validated) {
            $wf = Workflow::create([
                'name'          => $validated['name'],
                'workflow_type' => $validated['workflow_type'],
                'description'   => $validated['description'],
                'status'        => 'draft',
                'created_by'    => auth()->id(),
            ]);

            $wf->versions()->create([
                'version_number' => 1,
                'canvas'         => $validated['canvas'],
                'is_active'      => true,
                'created_by'     => auth()->id(),
            ]);

            $wf->update(['current_version' => 1]);

            return $wf;
        });

        activity()->log("Workflow created: {$workflow->name}");

        return response()->json(['success' => true, 'data' => $workflow], 201);
    }

    /**
     * PUT /api/admin/workflows/{id}
     * Save a new draft canvas version
     */
    public function update(Request $request, int $id)
    {
        $workflow  = Workflow::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'canvas'      => 'required|array',
            'canvas.nodes'=> 'required|array',
            'canvas.edges'=> 'required|array',
        ]);

        $this->validateCanvas($validated['canvas']);

        DB::transaction(function () use ($workflow, $validated) {
            // Deactivate current active version
            $workflow->versions()->where('is_active', true)->update(['is_active' => false]);

            $newVersion = ($workflow->current_version ?? 0) + 1;

            $workflow->versions()->create([
                'version_number' => $newVersion,
                'canvas'         => $validated['canvas'],
                'is_active'      => true,
                'created_by'     => auth()->id(),
            ]);

            $workflow->update([
                'name'            => $validated['name'] ?? $workflow->name,
                'description'     => $validated['description'] ?? $workflow->description,
                'current_version' => $newVersion,
                'status'          => 'draft',
            ]);
        });

        return response()->json(['success' => true, 'message' => "Workflow saved as draft v{$workflow->fresh()->current_version}."]);
    }

    /**
     * POST /api/admin/workflows/{id}/publish
     * Publish a draft workflow — activates it platform-wide
     */
    public function publish(int $id)
    {
        $workflow = Workflow::findOrFail($id);

        if ($workflow->status === 'published') {
            return response()->json(['success' => false, 'message' => 'Workflow is already published.'], 422);
        }

        $workflow->update(['status' => 'published', 'published_at' => now(), 'published_by' => auth()->id()]);

        activity()->log("Workflow published: {$workflow->name} v{$workflow->current_version}");

        return response()->json(['success' => true, 'message' => 'Workflow is now live.']);
    }

    /**
     * POST /api/admin/workflows/{id}/archive
     * Archive — stops it from being triggered on new events
     */
    public function archive(int $id)
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->update(['status' => 'archived']);

        activity()->log("Workflow archived: {$workflow->name}");

        return response()->json(['success' => true, 'message' => 'Workflow archived.']);
    }

    /**
     * GET /api/admin/workflows/{id}/versions
     * Version history
     */
    public function versions(int $id)
    {
        $workflow = Workflow::findOrFail($id);
        $versions = $workflow->versions()->select('id', 'version_number', 'is_active', 'created_at', 'created_by')->orderByDesc('version_number')->get();

        return response()->json(['success' => true, 'data' => $versions]);
    }

    /**
     * POST /api/admin/workflows/{id}/rollback/{version}
     * Roll back to a prior version
     */
    public function rollback(int $id, int $version)
    {
        $workflow       = Workflow::findOrFail($id);
        $targetVersion  = $workflow->versions()->where('version_number', $version)->firstOrFail();

        DB::transaction(function () use ($workflow, $targetVersion) {
            $workflow->versions()->where('is_active', true)->update(['is_active' => false]);
            $targetVersion->update(['is_active' => true]);
            $workflow->update(['current_version' => $targetVersion->version_number, 'status' => 'draft']);
        });

        activity()->log("Workflow rolled back: {$workflow->name} → v{$version}");

        return response()->json(['success' => true, 'message' => "Rolled back to v{$version}. Re-publish to activate."]);
    }

    /**
     * GET /api/admin/workflows/templates
     * Returns built-in starter templates for the canvas
     */
    public function templates()
    {
        return response()->json([
            'success' => true,
            'data'    => array_map(
                fn($key, $label) => [
                    'key'    => $key,
                    'label'  => $label,
                    'canvas' => $this->getBuiltInTemplate($key),
                ],
                array_keys(self::BUILT_IN_WORKFLOWS),
                array_values(self::BUILT_IN_WORKFLOWS)
            ),
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function validateCanvas(array $canvas): void
    {
        $nodes = $canvas['nodes'] ?? [];

        $hasStart = collect($nodes)->where('type', 'start')->count() === 1;
        $hasEnd   = collect($nodes)->where('type', 'end')->count() >= 1;
        $validTypes = collect($nodes)->every(fn($n) => in_array($n['type'] ?? '', self::NODE_TYPES));

        if (!$hasStart || !$hasEnd || !$validTypes) {
            abort(422, 'Invalid workflow canvas: must have exactly one start node, at least one end node, and valid node types.');
        }
    }

    private function getBuiltInTemplate(string $key): array
    {
        // Returns a minimal node/edge canvas definition for each template type
        $templates = [
            'merchant_onboarding' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'start',    'label' => 'Merchant Submitted',   'x' => 100, 'y' => 50],
                    ['id' => 'n2', 'type' => 'action',   'label' => 'KYC Auto-Verification', 'x' => 100, 'y' => 150],
                    ['id' => 'n3', 'type' => 'decision', 'label' => 'KYC Pass?',             'x' => 100, 'y' => 250],
                    ['id' => 'n4', 'type' => 'action',   'label' => 'Super Admin Review',    'x' => 250, 'y' => 350],
                    ['id' => 'n5', 'type' => 'action',   'label' => 'Request Re-KYC',        'x' => 0,   'y' => 350],
                    ['id' => 'n6', 'type' => 'end',      'label' => 'Approved',              'x' => 250, 'y' => 450],
                    ['id' => 'n7', 'type' => 'end',      'label' => 'Rejected',              'x' => 0,   'y' => 450],
                ],
                'edges' => [
                    ['from' => 'n1', 'to' => 'n2'],
                    ['from' => 'n2', 'to' => 'n3'],
                    ['from' => 'n3', 'to' => 'n4', 'label' => 'Pass'],
                    ['from' => 'n3', 'to' => 'n5', 'label' => 'Fail'],
                    ['from' => 'n4', 'to' => 'n6'],
                    ['from' => 'n5', 'to' => 'n7'],
                ],
            ],
            // Other templates follow same structure...
        ];

        return $templates[$key] ?? ['nodes' => [
            ['id' => 'n1', 'type' => 'start', 'label' => 'Start', 'x' => 100, 'y' => 50],
            ['id' => 'n2', 'type' => 'end',   'label' => 'End',   'x' => 100, 'y' => 200],
        ], 'edges' => [['from' => 'n1', 'to' => 'n2']]];
    }
}