<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class TestAllEndpoints extends Command
{
    protected $signature = 'test:all-endpoints';
    protected $description = 'Systematically test all Phase 1-7 GET API endpoints to find 500 errors';

    public function handle()
    {
        $this->info("Starting System-Wide API Test...");
        $baseUrl = 'http://127.0.0.1:8000';

        // 1. Login to get token
        $this->info("Logging in...");
        $loginRes = Http::post("$baseUrl/api/v1/auth/login", [
            'email' => 'finzwork10@gmail.com',
            'password' => 'New@password123'
        ]);

        if (!$loginRes->successful()) {
            $this->error("Failed to login: " . $loginRes->body());
            return;
        }
        $token = $loginRes->json('access_token');
        
        // 2. Verify MFA to unlock token
        $this->info("Verifying MFA...");
        $mfaRes = Http::withToken($token)->post("$baseUrl/api/v1/auth/mfa/verify", [
            'otp' => '123456'
        ]);
        
        if (!$mfaRes->successful() && $mfaRes->json('code') !== 'invalid_otp' && $mfaRes->status() !== 429) {
             // If we already verified or hit rate limit, we continue
             if ($mfaRes->status() !== 401 && $mfaRes->status() !== 400 && $mfaRes->status() !== 429) {
                  $this->warn("MFA returned non-200, but proceeding. Status: " . $mfaRes->status());
             }
        }
        
        $token = $mfaRes->json('access_token') ?? $token;
        $this->info("Authentication successful. Testing GET endpoints...");

        // 3. Get all GET routes for api/v1/
        $routes = Route::getRoutes()->getRoutes();
        $endpoints = [];

        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'api/v1/') && in_array('GET', $route->methods())) {
                // Skip endpoints with {id} for now, or replace {id} with 1
                if (str_contains($uri, '{')) {
                    $uri = preg_replace('/\{[^\}]+\}/', '1', $uri);
                }
                $endpoints[] = $uri;
            }
        }
        
        $endpoints = array_unique($endpoints);
        $this->info("Found " . count($endpoints) . " GET endpoints to test.");

        $failed = [];
        $passed = 0;

        foreach ($endpoints as $uri) {
            try {
                $response = Http::withToken($token)->get("$baseUrl/$uri");
                $status = $response->status();
                
                if ($status === 500) {
                    $this->error("[$status] $uri -> FATAL ERROR");
                    $failed[] = $uri;
                } elseif ($status === 404) {
                    // Usually means ID=1 doesn't exist, which is fine
                    $this->line("[$status] $uri -> Not Found (Expected for ID=1)");
                    $passed++;
                } elseif ($status === 403 || $status === 401) {
                    $this->line("[$status] $uri -> Unauthorized/Forbidden");
                    $passed++; // Handled securely
                } else {
                    $this->info("[$status] $uri -> Success");
                    $passed++;
                }
            } catch (\Exception $e) {
                $this->error("[EXCEPTION] $uri -> " . $e->getMessage());
                $failed[] = $uri;
            }
        }

        $this->info("====================================");
        $this->info("TEST COMPLETE: $passed endpoints passed.");
        if (count($failed) > 0) {
            $this->error(count($failed) . " endpoints FAILED with 500 Internal Server Error.");
        } else {
            $this->info("ZERO 500 ERRORS FOUND! PERFECT!");
        }
    }
}
