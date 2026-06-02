<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MergeSwaggerDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:merge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge static openapi.json (Phase 1-3) with generated api-docs.json (Phase 4-7)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $staticPath = public_path('openapi.json');
        $generatedPath = storage_path('api-docs/api-docs.json');

        if (!File::exists($staticPath)) {
            $this->error("Static openapi.json not found at {$staticPath}");
            return;
        }

        if (!File::exists($generatedPath)) {
            $this->error("Generated api-docs.json not found at {$generatedPath}. Run l5-swagger:generate first.");
            return;
        }

        $staticDocs = json_decode(File::get($staticPath), true);
        $generatedDocs = json_decode(File::get($generatedPath), true);

        // Merge Paths
        $generatedDocs['paths'] = array_merge(
            $staticDocs['paths'] ?? [],
            $generatedDocs['paths'] ?? []
        );

        // Merge Components (Schemas, SecuritySchemes, etc)
        if (isset($staticDocs['components'])) {
            foreach ($staticDocs['components'] as $key => $component) {
                if (!isset($generatedDocs['components'][$key])) {
                    $generatedDocs['components'][$key] = [];
                }
                $generatedDocs['components'][$key] = array_merge(
                    $component,
                    $generatedDocs['components'][$key]
                );
            }
        }

        // Merge Tags
        if (isset($staticDocs['tags'])) {
            $existingTags = array_column($generatedDocs['tags'] ?? [], 'name');
            foreach ($staticDocs['tags'] as $tag) {
                if (!in_array($tag['name'], $existingTags)) {
                    $generatedDocs['tags'][] = $tag;
                }
            }
        }

        // Save merged JSON back to the generated path so Swagger UI serves it
        File::put($generatedPath, json_encode($generatedDocs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Successfully merged Shruti's legacy APIs (Phase 1-3) into the dynamic Swagger docs!");
    }
}
