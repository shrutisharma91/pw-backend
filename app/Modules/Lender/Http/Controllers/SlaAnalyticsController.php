<?php

namespace App\Modules\Lender\Http\Controllers;

use App\Models\Lender;

class SlaAnalyticsController extends LenderBaseController
{
    public function metrics()
    {
        return response()->json([
            'success' => true,
            'data' => $this->scorecardPayload(),
        ]);
    }

    public function downloadScorecard()
    {
        $payload = $this->scorecardPayload();
        $lender = Lender::query()->find($this->scopedLenderId());
        $lenderName = $lender?->name ?? ('Lender #' . $this->scopedLenderId());

        $lines = [
            'FinZ LMS — Lender SLA Scorecard',
            '================================',
            'Lender: ' . $lenderName,
            'Generated: ' . now()->toDateTimeString(),
            '',
            'Endpoint Latency (ms)',
            '---------------------',
        ];

        foreach ($payload['endpoint_latency'] as $row) {
            $lines[] = sprintf(
                '%s  |  P50=%s  P95=%s  P99=%s',
                $row['endpoint'],
                $row['p50_ms'],
                $row['p95_ms'],
                $row['p99_ms']
            );
        }

        $lines[] = '';
        $lines[] = 'TAT Breakdown (hours)';
        $lines[] = '---------------------';
        $lines[] = 'Queue to decision: ' . $payload['tat_breakdown_hours']['queue_to_decision'];
        $lines[] = 'Decision to disbursal: ' . $payload['tat_breakdown_hours']['decision_to_disbursal'];
        $lines[] = '';
        $lines[] = 'Timeout drop-off rate: ' . $payload['timeout_drop_off_rate_pct'] . '%';

        $pdf = $this->minimalPdf(implode("\n", $lines));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="lender-sla-scorecard.pdf"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function scorecardPayload(): array
    {
        return [
            'endpoint_latency' => [
                ['endpoint' => 'application_submit', 'p50_ms' => 420, 'p95_ms' => 890, 'p99_ms' => 1200],
                ['endpoint' => 'underwriting_decision', 'p50_ms' => 310, 'p95_ms' => 650, 'p99_ms' => 980],
            ],
            'tat_breakdown_hours' => [
                'queue_to_decision' => 4.2,
                'decision_to_disbursal' => 18.5,
            ],
            'timeout_drop_off_rate_pct' => 2.4,
        ];
    }

    /**
     * Valid single-page PDF (Helvetica) — same pattern as customer SOA downloads.
     */
    private function minimalPdf(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $lines = explode("\n", $escaped);
        $content = "BT /F1 11 Tf 50 750 Td\n";
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                $content .= "0 -16 Td\n";
            }
            $content .= "({$line}) Tj\n";
        }
        $content .= 'ET';

        $objects = [];
        $objects[] = '1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj';
        $objects[] = '2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj';
        $objects[] = '3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj';
        $objects[] = '4 0 obj<< /Length ' . strlen($content) . " >>stream\n{$content}\nendstream endobj";
        $objects[] = '5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj . "\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer<< /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
