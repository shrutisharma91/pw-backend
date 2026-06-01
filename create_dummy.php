<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$merchant = \App\Models\Merchant::first();
if ($merchant) {
    if (\App\Models\MerchantCategory::count() === 0) {
        \App\Models\MerchantCategory::create([
            'merchant_id' => $merchant->id,
            'name' => 'Electronics'
        ]);
    }

    if (\App\Models\VerificationLog::count() === 0) {
        \App\Models\VerificationLog::create([
            'merchant_id' => $merchant->id,
            'api_type' => 'GST',
            'status' => 'Failed',
            'provider' => 'Karza',
            'request_payload' => json_encode(['request' => 'verify_gst']),
            'response_payload' => json_encode(['error' => 'API timeout']),
        ]);
    }
    echo "Dummy data created successfully!\n";
} else {
    echo "No merchant found to attach to.\n";
}
