<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live health checks for third-party integrations (Phase 13 — Screen 53).
 */
class IntegrationHealthService
{
    /**
     * Run a live health check against an integration endpoint.
     *
     * @return array{
     *     status: string,
     *     last_checked_at: string,
     *     last_success_at: string|null,
     *     response_time_ms: int|null,
     *     http_status?: int|null,
     *     error?: string|null
     * }
     */
    public function check(Integration $integration): array
    {
        $checkedAt = now();

        if (empty($integration->base_url)) {
            return $this->result('down', $checkedAt, null, null, null, 'Base URL is not configured.');
        }

        if (!$integration->is_active) {
            return $this->result('down', $checkedAt, null, null, null, 'Integration is disabled.');
        }

        $timeout   = max(1, (int) ($integration->timeout_seconds ?? 30));
        $start     = microtime(true);
        $httpStatus = null;
        $error      = null;
        $isSuccess  = false;

        try {
            $request = Http::timeout($timeout)->acceptJson();

            if ($integration->api_key_enc) {
                $apiKey = Crypt::decryptString($integration->api_key_enc);
                $request = $request->withHeaders(['Authorization' => "Bearer {$apiKey}"]);
            }

            $response   = $request->get(rtrim($integration->base_url, '/') . '/health');
            $httpStatus = $response->status();
            $isSuccess  = $response->successful();

            if (!$isSuccess) {
                $error = "HTTP {$httpStatus}";
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning("Integration health check failed for #{$integration->id}: {$error}");
        }

        $responseTimeMs = (int) round((microtime(true) - $start) * 1000);

        DB::table('integration_call_logs')->insert([
            'integration_id'   => $integration->id,
            'endpoint'         => '/health',
            'http_status'      => $httpStatus,
            'response_time_ms' => $responseTimeMs,
            'is_success'       => $isSuccess,
            'error_code'       => $isSuccess ? null : ($httpStatus ? (string) $httpStatus : 'connection_error'),
            'cost'             => 0,
            'created_at'       => $checkedAt,
            'updated_at'       => $checkedAt,
        ]);

        $lastSuccessAt = $isSuccess
            ? $checkedAt->toISOString()
            : $this->lastSuccessAt($integration->id);

        $status = $isSuccess
            ? 'up'
            : ($httpStatus !== null && $httpStatus >= 500 ? 'down' : 'degraded');

        return $this->result($status, $checkedAt, $lastSuccessAt, $responseTimeMs, $httpStatus, $error);
    }

    private function lastSuccessAt(int $integrationId): ?string
    {
        $timestamp = DB::table('integration_call_logs')
            ->where('integration_id', $integrationId)
            ->where('is_success', true)
            ->orderByDesc('created_at')
            ->value('created_at');

        return $timestamp ? (string) $timestamp : null;
    }

    private function result(
        string $status,
        \DateTimeInterface $checkedAt,
        ?string $lastSuccessAt,
        ?int $responseTimeMs,
        ?int $httpStatus = null,
        ?string $error = null,
    ): array {
        return array_filter([
            'status'           => $status,
            'last_checked_at'  => $checkedAt->toISOString(),
            'last_success_at'  => $lastSuccessAt,
            'response_time_ms' => $responseTimeMs,
            'http_status'      => $httpStatus,
            'error'            => $error,
        ], fn ($value) => $value !== null);
    }
}
