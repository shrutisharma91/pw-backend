<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

for ($i = 1; $i <= 10; $i++) {
    $merchant = \App\Models\Merchant::create([
        'business_name' => 'Demo Deployed Merchant ' . $i,
        'gst_number' => '22AAAAA0000A1Z' . $i,
        'pan_number' => 'AAAAA0000' . $i,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    \App\Models\Store::create([
        'merchant_id' => $merchant->id,
        'name' => 'Main Branch ' . $i,
        'address' => 'Deployed Test Address ' . $i,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

echo "Created 10 Dummy Merchants and Stores!\n";
