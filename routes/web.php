<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', [HealthController::class, 'check']);

// Serve local, classic Swagger UI
Route::view('/swagger', 'swagger');
