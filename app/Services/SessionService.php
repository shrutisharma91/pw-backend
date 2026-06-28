<?php

namespace App\Services;

use App\Models\AdminSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionService
{
    public function revokeSession(AdminSession $session): bool
    {
        if (! $session->is_active) {
            return false;
        }

        $session->update([
            'is_active'     => false,
            'logged_out_at' => now(),
        ]);

        return true;
    }

    /**
     * Force-logout every active session currently flagged as suspicious,
     * platform-wide (across all users). Returns the number revoked.
     */
    public function revokeAllSuspicious(?int $revokedByAdminId = null): int
    {
        return DB::transaction(function () use ($revokedByAdminId) {
            $revoked = AdminSession::query()
                ->where('is_active', true)
                ->where('is_suspicious', true)
                ->update([
                    'is_active'     => false,
                    'logged_out_at' => now(),
                ]);

            if ($revoked > 0) {
                Log::warning(
                    "Revoke-all-suspicious: {$revoked} suspicious session(s) revoked by admin " . ($revokedByAdminId ?? 'unknown')
                );
            }

            return $revoked;
        });
    }

    /**
     * @param  array<int>  $sessionIds
     * @return array{revoked_count: int, failed_count: int}
     */
    public function bulkRevokeSessions(array $sessionIds, ?int $revokedByAdminId = null): array
    {
        $sessionIds = array_values(array_unique(array_map('intval', $sessionIds)));

        return DB::transaction(function () use ($sessionIds, $revokedByAdminId) {
            $revokedCount = 0;
            $failedCount  = 0;

            $sessions = AdminSession::query()
                ->whereIn('id', $sessionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($sessionIds as $sessionId) {
                $session = $sessions->get($sessionId);

                if (! $session || ! $this->revokeSession($session)) {
                    $failedCount++;
                    continue;
                }

                $revokedCount++;
            }

            if ($revokedCount > 0) {
                Log::warning(
                    "Bulk session revoke: {$revokedCount} session(s) revoked by admin " . ($revokedByAdminId ?? 'unknown')
                );
            }

            return [
                'revoked_count' => $revokedCount,
                'failed_count'  => $failedCount,
            ];
        });
    }
}
