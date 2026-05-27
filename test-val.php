<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$v = validator(['action' => 'enable', 'channel' => 'sms'], ['channel' => 'required_if:action,enable,change_channel|in:sms,totp']);
echo json_encode($v->errors());
