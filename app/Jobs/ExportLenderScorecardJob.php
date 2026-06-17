<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ExportReadyNotification;

class ExportLenderScorecardJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        private string  $period,
        private string  $format,
        private ?int    $lenderId,
        private int     $requestedBy
    ) {}

    public function handle(): void
    {
        // 1. Pull scorecard data
        [$start, $end] = $this->resolveDateRange($this->period);

        $query = DB::table('lenders')
            ->leftJoin('loans', 'loans.lender_id', '=', 'lenders.id')
            ->whereBetween('loans.created_at', [$start, $end])
            ->select(
                'lenders.id',
                'lenders.name',
                DB::raw('COUNT(loans.id) as total_applications'),
                DB::raw("SUM(CASE WHEN loans.lender_status = 'approved' THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN loans.status = 'disbursed' THEN 1 ELSE 0 END) as disbursed"),
                DB::raw('SUM(CASE WHEN loans.is_npa = true THEN 1 ELSE 0 END) as npa_count'),
                DB::raw('SUM(loans.loan_amount) as total_volume'),
                DB::raw('ROUND(AVG(EXTRACT(EPOCH FROM (loans.disbursed_at - loans.approved_at)) / 86400)::numeric, 1) as avg_disbursal_days')
            )
            ->groupBy('lenders.id', 'lenders.name');

        if ($this->lenderId) {
            $query->where('lenders.id', $this->lenderId);
        }

        $rows = $query->get()->toArray();

        // 2. Generate file
        $filename = 'exports/lender-scorecard-' . $this->period . '-' . now()->format('Ymd-His') . '.' . $this->format;
        $content  = $this->generateFile($rows, $this->format);

        Storage::disk('r2')->put($filename, $content);

        // 3. Notify the requesting admin
        $disk = Storage::disk('r2');
        try {
            $signedUrl = $disk->temporaryUrl($filename, now()->addHours(24));
        } catch (\Throwable $e) {
            // Local stand-in for R2 may not support temporaryUrl() — return best-effort URL/path.
            $signedUrl = $disk->url($filename) ?: $disk->path($filename);
        }

        $admin = DB::table('users')->where('id', $this->requestedBy)->first();
        if ($admin) {
            Notification::route('mail', $admin->email)
                ->notify(new ExportReadyNotification('Lender Scorecard Export', $signedUrl));
        }
    }

    private function generateFile(array $rows, string $format): string
    {
        if ($format === 'csv') {
            if (empty($rows)) return '';
            $headers = array_keys((array) $rows[0]);
            $lines   = [implode(',', $headers)];
            foreach ($rows as $row) {
                $lines[] = implode(',', array_values((array) $row));
            }
            return implode("\n", $lines);
        }

        // For xlsx/pdf — return JSON as placeholder; real implementation uses PhpSpreadsheet / DomPDF
        return json_encode($rows, JSON_PRETTY_PRINT);
    }

    private function resolveDateRange(string $period): array
    {
        return match ($period) {
            '7d'  => [now()->subDays(7)->toDateString(),  now()->toDateString()],
            '30d' => [now()->subDays(30)->toDateString(), now()->toDateString()],
            '90d' => [now()->subDays(90)->toDateString(), now()->toDateString()],
            '1y'  => [now()->subYear()->toDateString(),   now()->toDateString()],
            default => [now()->subDays(30)->toDateString(), now()->toDateString()],
        };
    }

    public function failed(\Throwable $e): void
    {
        \Log::error("ExportLenderScorecardJob failed for admin #{$this->requestedBy}: " . $e->getMessage());
    }
}