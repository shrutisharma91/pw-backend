<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ticket;
use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| GlobalSearchController
|--------------------------------------------------------------------------
| Screen 08 — Global Search & Command Palette (Cmd+K)
|
| APIs:
|   GET  /api/v1/admin/search          → search across all entities
|   GET  /api/v1/admin/search/recent   → recent searches for this user
|   POST /api/v1/admin/search/save     → save a search
|   DELETE /api/v1/admin/search/saved/{id} → delete saved search
|
| Searches across: users, merchants, loans, stores, tickets
| Returns grouped results by category
*/

class GlobalSearchController extends Controller
{
    // ------------------------------------------------------------------
    // GET /api/v1/admin/search?q=rajesh&categories=users,merchants
    // ------------------------------------------------------------------
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $query      = $request->q;
        $categories = $request->get('categories', 'all');
        $results    = [];

        // -----------------------------------------------
        // USERS — search by name, email, mobile
        // -----------------------------------------------
        if ($categories === 'all' || str_contains($categories, 'users')) {
            $users = User::where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%")
                      ->orWhere('mobile', 'like', "%{$query}%");
                })
                ->limit(5)
                ->get(['id', 'name', 'email', 'role', 'is_active']);

            if ($users->isNotEmpty()) {
                $results['users'] = [
                    'label'   => 'Users',
                    'count'   => $users->count(),
                    'items'   => $users->map(fn($u) => [
                        'id'       => $u->id,
                        'label'    => $u->name,
                        'sublabel' => $u->email,
                        'badge'    => $u->role,
                        'url'      => "/users/{$u->id}",
                        'type'     => 'user',
                    ]),
                ];
            }
        }

        // -----------------------------------------------
        // MERCHANTS — when merchants table exists
        // -----------------------------------------------
        if ($categories === 'all' || str_contains($categories, 'merchants')) {
            // Replace when merchant model is ready:
            // $merchants = Merchant::where('business_name', 'like', "%{$query}%")->limit(5)->get();
            $results['merchants'] = [
                'label' => 'Merchants',
                'count' => 0,
                'items' => [], // populate when merchant tables exist
            ];
        }

        // -----------------------------------------------
        // LOANS — when loans table exists
        // -----------------------------------------------
        if ($categories === 'all' || str_contains($categories, 'loans')) {
            $results['loans'] = [
                'label' => 'Loans',
                'count' => 0,
                'items' => [], // populate when loan tables exist
            ];
        }

        // -----------------------------------------------
        // TICKETS — when tickets table exists
        // -----------------------------------------------
        if ($categories === 'all' || str_contains($categories, 'tickets')) {
            $tickets = Ticket::query()
                ->where(function ($q) use ($query) {
                    $q->where('ticket_number', 'ILIKE', "%{$query}%")
                        ->orWhere('subject', 'ILIKE', "%{$query}%")
                        ->orWhere('reporter_name', 'ILIKE', "%{$query}%");
                })
                ->limit(5)
                ->get(['id', 'ticket_number', 'subject', 'status', 'priority']);

            if ($tickets->isNotEmpty()) {
                $results['tickets'] = [
                    'label' => 'Tickets',
                    'count' => $tickets->count(),
                    'items' => $tickets->map(fn ($t) => [
                        'id'       => $t->id,
                        'label'    => $t->ticket_number,
                        'sublabel' => $t->subject,
                        'badge'    => $t->status,
                        'url'      => "/tickets/{$t->id}",
                        'type'     => 'ticket',
                    ]),
                ];
            }
        }

        // Save this search to recent searches
        $this->saveToRecent(auth()->id(), $query);

        $totalResults = collect($results)->sum('count');

        return response()->json([
            'success'       => true,
            'query'         => $query,
            'total_results' => $totalResults,
            'results'       => $results,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/search/recent
    // Recent searches for the logged-in admin
    // ------------------------------------------------------------------
    public function recentSearches()
    {
        $userId  = auth()->id();
        $recents = \Illuminate\Support\Facades\Cache::get("recent_searches_{$userId}", []);

        return response()->json([
            'success' => true,
            'data'    => array_slice(array_reverse($recents), 0, 10), // last 10
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/search/save
    // Body: { "query": "merchants under review", "label": "Pending KYC" }
    // ------------------------------------------------------------------
    public function saveSearch(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:200',
            'label' => 'nullable|string|max:100',
        ]);

        $userId  = auth()->id();
        $saved   = \Illuminate\Support\Facades\Cache::get("saved_searches_{$userId}", []);

        $saved[] = [
            'id'         => uniqid(),
            'query'      => $request->query,
            'label'      => $request->label ?? $request->query,
            'created_at' => now()->toISOString(),
        ];

        \Illuminate\Support\Facades\Cache::forever("saved_searches_{$userId}", $saved);

        return response()->json([
            'success' => true,
            'message' => 'Search saved.',
        ]);
    }

    // ------------------------------------------------------------------
    // DELETE /api/v1/admin/search/saved/{id}
    // ------------------------------------------------------------------
    public function deleteSavedSearch(string $id)
    {
        $userId = auth()->id();
        $saved  = \Illuminate\Support\Facades\Cache::get("saved_searches_{$userId}", []);

        $saved = array_filter($saved, fn($s) => $s['id'] !== $id);

        \Illuminate\Support\Facades\Cache::forever("saved_searches_{$userId}", array_values($saved));

        return response()->json([
            'success' => true,
            'message' => 'Saved search deleted.',
        ]);
    }

    // ------------------------------------------------------------------
    // Private: Add to recent searches cache
    // ------------------------------------------------------------------
    private function saveToRecent(int $userId, string $query): void
    {
        $key     = "recent_searches_{$userId}";
        $recents = \Illuminate\Support\Facades\Cache::get($key, []);

        // Remove duplicate if exists
        $recents = array_filter($recents, fn($r) => $r['query'] !== $query);

        $recents[] = [
            'query'      => $query,
            'searched_at' => now()->toISOString(),
        ];

        // Keep only last 20
        $recents = array_slice(array_values($recents), -20);

        \Illuminate\Support\Facades\Cache::forever($key, $recents);
    }
}