<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Cache;

$user_id = 2; // from their token payload "id": 2
$cachePrefix = 'mfa_otp_';

Cache::put($cachePrefix . $user_id, [
    'otp' => '123456',
    'attempts' => 0,
    'created_at' => now()->toISOString()
], 300);

$val = Cache::get($cachePrefix . $user_id);
echo json_encode($val);
