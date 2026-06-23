<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\HealthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', [HealthController::class, 'check']);

// Time-limited signed URLs for document preview/share (local R2 stand-in)
Route::get('/files/documents/{id}', [DocumentFileController::class, 'serve'])
    ->whereNumber('id')
    ->name('documents.serve');

// Serve local, classic Swagger UI
Route::view('/swagger', 'swagger');
