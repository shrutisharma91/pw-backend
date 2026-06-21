<?php

$dir = __DIR__;

// --- Update Models ---
$models = [
    'ConsentLog' => "protected \$fillable = ['customer_id', 'merchant_id', 'consent_type', 'version', 'payload', 'ip_address', 'device', 'status'];\n\n    protected \$casts = ['payload' => 'array'];\n\n    public function customer() { return \$this->belongsTo(Customer::class); }\n    public function merchant() { return \$this->belongsTo(Merchant::class); }",
    'DataPrincipalRequest' => "protected \$fillable = ['customer_id', 'request_type', 'status', 'resolution_notes'];\n\n    public function customer() { return \$this->belongsTo(Customer::class); }",
    'ComplianceReport' => "protected \$fillable = ['report_type', 'status', 'generated_by', 'file_url', 'parameters'];\n\n    protected \$casts = ['parameters' => 'array'];\n\n    public function generator() { return \$this->belongsTo(User::class, 'generated_by'); }"
];

foreach ($models as $name => $fillable) {
    $path = "$dir/app/Models/$name.php";
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $content = preg_replace('/\{/', "{\n    $fillable\n", $content, 1);
        file_put_contents($path, $content);
        echo "Updated Model: $name\n";
    }
}

// --- Update Migrations ---
$migrations = [
    'add_hash_to_audit_logs_table' => [
        'up' => "\$table->string('hash')->nullable();\n            \$table->string('previous_hash')->nullable();",
        'down' => "\$table->dropColumn(['hash', 'previous_hash']);"
    ],
    'create_consent_logs_table' => [
        'up' => "\$table->id();\n            \$table->foreignId('customer_id')->constrained('customers');\n            \$table->foreignId('merchant_id')->nullable()->constrained('merchants');\n            \$table->string('consent_type'); // KFS, terms, data_sharing, marketing\n            \$table->string('version');\n            \$table->json('payload');\n            \$table->string('ip_address')->nullable();\n            \$table->string('device')->nullable();\n            \$table->string('status')->default('Active'); // Active, Withdrawn\n            \$table->timestamps();",
        'down' => "\$table->dropIfExists('consent_logs');"
    ],
    'create_data_principal_requests_table' => [
        'up' => "\$table->id();\n            \$table->foreignId('customer_id')->constrained('customers');\n            \$table->string('request_type'); // access, correction, erasure\n            \$table->string('status')->default('Pending'); // Pending, Completed, Rejected\n            \$table->text('resolution_notes')->nullable();\n            \$table->timestamps();",
        'down' => "\$table->dropIfExists('data_principal_requests');"
    ],
    'create_compliance_reports_table' => [
        'up' => "\$table->id();\n            \$table->string('report_type'); // RBI monthly, DPDP access\n            \$table->string('status')->default('Pending');\n            \$table->foreignId('generated_by')->nullable()->constrained('users');\n            \$table->string('file_url')->nullable();\n            \$table->json('parameters')->nullable();\n            \$table->timestamps();",
        'down' => "\$table->dropIfExists('compliance_reports');"
    ]
];

$migrationFiles = glob("$dir/database/migrations/*.php");
foreach ($migrationFiles as $file) {
    foreach ($migrations as $key => $schema) {
        if (strpos($file, $key) !== false) {
            $content = file_get_contents($file);
            
            if ($key === 'add_hash_to_audit_logs_table') {
                $content = preg_replace('/Schema::table\(\'audit_logs\', function \(Blueprint \$table\) \{(.*?)\}\);/s', "Schema::table('audit_logs', function (Blueprint \$table) {\n            {$schema['up']}\n        });", $content);
                $content = preg_replace('/Schema::table\(\'audit_logs\', function \(Blueprint \$table\) \{(.*?)\}\);/s', "Schema::table('audit_logs', function (Blueprint \$table) {\n            {$schema['down']}\n        });", $content, 1, $count); // wait, down is tricky.
                
                // Let's just do a simple replace
                $content = str_replace(
                    "Schema::table('audit_logs', function (Blueprint \$table) {\n            //\n        });",
                    "Schema::table('audit_logs', function (Blueprint \$table) {\n            {$schema['up']}\n        });",
                    $content
                );
                // second match for down
                $pos = strrpos($content, "Schema::table('audit_logs', function (Blueprint \$table) {");
                if ($pos !== false) {
                    $content = substr_replace($content, "Schema::table('audit_logs', function (Blueprint \$table) {\n            {$schema['down']}\n        });", $pos, strlen("Schema::table('audit_logs', function (Blueprint \$table) {\n            //\n        });"));
                }
            } else {
                $content = preg_replace('/Schema::create\(.*?, function \(Blueprint \$table\) \{(.*?)\}\);/s', "Schema::create('".str_replace('create_', '', str_replace('_table', '', $key))."', function (Blueprint \$table) {\n            {$schema['up']}\n        });", $content);
            }
            file_put_contents($file, $content);
            echo "Updated Migration: $key\n";
        }
    }
}
