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

class RunCustomReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;  // 5 minutes — reports can be large

    // Whitelist mirrors CustomReportController — never trust user input directly
    private const ALLOWED_MODULES = [
        'loans', 'merchants', 'stores', 'payments', 'lenders',
    ];

    public function __construct(
        private array  $definition,
        private string $format,
        private int    $requestedBy,
        private string $reportName
    ) {}

    public function handle(): void
    {
        // 1. Validate module again (safety net — controller already validates, but jobs can be retried)
        $module = $this->definition['module'] ?? '';
        if (!in_array($module, self::ALLOWED_MODULES)) {
            \Log::error("RunCustomReportJob: invalid module '{$module}' for admin #{$this->requestedBy}");
            return;
        }

        // 2. Execute query
        $rows = $this->executeQuery($this->definition);

        // 3. Generate export file
        $safeReportName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $this->reportName);
        $filename = 'exports/custom-report-' . $safeReportName . '-' . now()->format('Ymd-His') . '.' . $this->format;
        $content  = $this->generateFile($rows, $this->format);

        Storage::disk('r2')->put($filename, $content);

        // 4. Signed URL valid 24 hours
        $signedUrl = Storage::disk('r2')->temporaryUrl($filename, now()->addHours(24));

        // 5. Notify admin
        $admin = DB::table('users')->where('id', $this->requestedBy)->first();
        if ($admin) {
            Notification::route('mail', $admin->email)
                ->notify(new ExportReadyNotification("Custom Report: {$this->reportName}", $signedUrl));
        }
    }

    private function executeQuery(array $config): array
    {
        $module  = $config['module'];
        $fields  = $config['fields']  ?? ['*'];
        $filters = $config['filters'] ?? [];
        $groupBy = $config['group_by'] ?? [];
        $limit   = min((int) ($config['limit'] ?? 50000), 50000);  // hard cap at 50k rows
        $orderBy = $config['order_by'] ?? null;
        $orderDir= in_array($config['order_dir'] ?? 'desc', ['asc', 'desc']) ? $config['order_dir'] : 'desc';

        $query = DB::table($module)->select($fields);

        foreach ($filters as $filter) {
            $op    = $filter['operator'] ?? '=';
            $field = $filter['field'];
            $value = $filter['value'];

            match ($op) {
                'like'    => $query->where($field, 'LIKE', "%{$value}%"),
                'in'      => $query->whereIn($field, (array) $value),
                'between' => $query->whereBetween($field, (array) $value),
                default   => $query->where($field, $op, $value),
            };
        }

        if (!empty($groupBy)) {
            $query->groupBy($groupBy);
        }

        if ($orderBy) {
            $query->orderBy($orderBy, $orderDir);
        }

        return $query->limit($limit)->get()->toArray();
    }

    private function generateFile(array $rows, string $format): string
    {
        if (empty($rows)) {
            return $format === 'json' ? '[]' : '';
        }

        return match ($format) {
            'csv'   => $this->toCsv($rows),
            'json'  => json_encode($rows, JSON_PRETTY_PRINT),
            default => $this->toCsv($rows),   // xlsx/pdf — placeholder; swap in PhpSpreadsheet / DomPDF
        };
    }

    private function toCsv(array $rows): string
    {
        $headers = array_keys((array) $rows[0]);
        $lines   = [implode(',', $headers)];

        foreach ($rows as $row) {
            $values  = array_values((array) $row);
            $escaped = array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $values);
            $lines[] = implode(',', $escaped);
        }

        return implode("\n", $lines);
    }

    public function failed(\Throwable $e): void
    {
        \Log::error("RunCustomReportJob failed — report: '{$this->reportName}', admin #{$this->requestedBy}: " . $e->getMessage());
    }
}
