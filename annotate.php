<?php

$targetFiles = [
    'app/Http/Controllers/Admin/MerchantController.php',
    'app/Http/Controllers/Admin/VerificationLogController.php',
    'app/Http/Controllers/Admin/MerchantAgreementController.php',
    'app/Http/Controllers/Admin/StoreController.php',
    'app/Http/Controllers/Admin/ProductController.php',
    'app/Http/Controllers/Admin/CategoryController.php',
    'app/Http/Controllers/Admin/BrandController.php',
    'app/Http/Controllers/Admin/MerchantCategoryController.php',
    'app/Http/Controllers/Admin/LenderController.php',
    'app/Http/Controllers/Admin/LenderWaterfallController.php',
    'app/Http/Controllers/Admin/LenderRuleController.php',
    'app/Http/Controllers/Admin/LenderSlaController.php',
    'app/Http/Controllers/EmiTypeController.php',
    'app/Http/Controllers/TenureSlabController.php',
    'app/Http/Controllers/OfferController.php',
];

foreach ($targetFiles as $path) {
    if (!file_exists($path)) {
        echo "Missing: $path\n";
        continue;
    }
    
    $content = file_get_contents($path);
    
    // Add use OpenApi statement
    if (strpos($content, 'use OpenApi\Attributes as OA;') === false) {
        $content = preg_replace('/namespace (.*?);/', "namespace $1;\n\nuse OpenApi\\Attributes as OA;", $content, 1);
    }
    
    $lines = explode("\n", $content);
    $newLines = [];
    $className = basename($path, '.php');
    $tag = str_replace('Controller', '', $className);
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        
        // Match public methods
        if (preg_match('/^(\s*)public function ([a-zA-Z0-9_]+)\s*\(/', $line, $matches)) {
            $spaces = $matches[1];
            $methodName = $matches[2];
            
            // Look back to see if it already has #[OA\
            $hasAnnotation = false;
            for ($j = $i - 1; $j >= 0 && $j >= $i - 10; $j--) {
                if (strpos($lines[$j], '#[OA\\') !== false) {
                    $hasAnnotation = true;
                    break;
                }
                if (strpos(trim($lines[$j]), '}') === 0) {
                    break;
                }
            }
            
            if (!$hasAnnotation) {
                // Determine HTTP Method
                $httpMethod = 'Get';
                if (in_array($methodName, ['store', 'bulkApprove', 'bulkReject', 'approve', 'reject', 'reKyc', 'bulkReKyc', 'suspend', 'sendNotice', 'escalateToRisk', 'retry', 'generate', 'bulkFinancingToggle', 'flag', 'delist', 'archive', 'setFinancingRules', 'mapToMaster', 'toggle', 'testConnection', 'simulate'])) {
                    $httpMethod = 'Post';
                } elseif (in_array($methodName, ['update'])) {
                    $httpMethod = 'Put';
                } elseif (in_array($methodName, ['destroy'])) {
                    $httpMethod = 'Delete';
                }
                
                // Add annotation lines
                $newLines[] = $spaces . "#[OA\\$httpMethod(";
                $newLines[] = $spaces . "    path: \"/api/v1/auto/$tag/$methodName\",";
                $newLines[] = $spaces . "    summary: \"$methodName $tag\",";
                $newLines[] = $spaces . "    security: [[\"sanctum\" => []]],";
                $newLines[] = $spaces . "    tags: [\"$tag\"],";
                $newLines[] = $spaces . "    responses: [";
                $newLines[] = $spaces . "        new OA\Response(response: 200, description: \"Success\")";
                $newLines[] = $spaces . "    ]";
                $newLines[] = $spaces . ")]";
            }
        }
        
        $newLines[] = $line;
    }
    
    file_put_contents($path, implode("\n", $newLines));
    echo "Annotated: $path\n";
}
echo "Done.\n";
