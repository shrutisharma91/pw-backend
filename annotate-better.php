<?php

exec('php artisan route:list --json', $output);
$routesJson = implode("\n", $output);
$routes = json_decode($routesJson, true);

if (!$routes) {
    die("Could not parse routes.json\n");
}

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

// Re-map routes by Controller@action
$actionMap = [];
foreach ($routes as $route) {
    $action = $route['action']; // e.g. App\Http\Controllers\Admin\MerchantController@index
    if (!$action || strpos($action, '@') === false) continue;
    
    $method = explode('|', $route['method'])[0]; // e.g. GET|HEAD -> GET
    // Normalize method
    $method = ucfirst(strtolower($method));
    
    $uri = '/' . ltrim($route['uri'], '/');
    
    $actionMap[$action] = [
        'method' => $method,
        'uri' => $uri,
    ];
}

foreach ($targetFiles as $path) {
    if (!file_exists($path)) {
        echo "Missing: $path\n";
        continue;
    }
    
    $content = file_get_contents($path);
    
    // Extract namespace
    preg_match('/namespace (.*?);/', $content, $nsMatch);
    $namespace = $nsMatch[1] ?? '';
    
    $className = basename($path, '.php');
    $tag = str_replace('Controller', '', $className);
    $fqcn = $namespace . '\\' . $className;
    
    // Add use OpenApi statement if missing
    if (strpos($content, 'use OpenApi\Attributes as OA;') === false) {
        $content = preg_replace('/namespace (.*?);/', "namespace $1;\n\nuse OpenApi\\Attributes as OA;", $content, 1);
    }
    
    $lines = explode("\n", $content);
    $newLines = [];
    
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
                // Find matching route
                $actionKey = $fqcn . '@' . $methodName;
                if (isset($actionMap[$actionKey])) {
                    $routeInfo = $actionMap[$actionKey];
                    $httpMethod = $routeInfo['method'];
                    $uri = $routeInfo['uri'];
                    
                    // Extract path parameters {id}, {merchantId}, etc.
                    preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $uri, $paramMatches);
                    $params = $paramMatches[1];
                    
                    // Add annotation lines
                    $newLines[] = $spaces . "#[OA\\$httpMethod(";
                    $newLines[] = $spaces . "    path: \"$uri\",";
                    $newLines[] = $spaces . "    summary: \"$methodName $tag\",";
                    // Add security padlock
                    $newLines[] = $spaces . "    security: [[\"sanctum\" => []]],";
                    $newLines[] = $spaces . "    tags: [\"$tag\"],";
                    
                    if (count($params) > 0) {
                        $newLines[] = $spaces . "    parameters: [";
                        foreach ($params as $idx => $param) {
                            $comma = ($idx == count($params) - 1) ? "" : ",";
                            $newLines[] = $spaces . "        new OA\Parameter(name: \"$param\", in: \"path\", required: true)$comma";
                        }
                        $newLines[] = $spaces . "    ],";
                    }
                    
                    $newLines[] = $spaces . "    responses: [";
                    $newLines[] = $spaces . "        new OA\Response(response: 200, description: \"Success\")";
                    $newLines[] = $spaces . "    ]";
                    $newLines[] = $spaces . ")]";
                }
            }
        }
        
        $newLines[] = $line;
    }
    
    file_put_contents($path, implode("\n", $newLines));
    echo "Annotated: $path\n";
}
echo "Done.\n";
