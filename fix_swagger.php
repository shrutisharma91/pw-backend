<?php

$dir = __DIR__ . '/app/Http/Controllers/Admin';
$files = glob("$dir/*.php");

foreach ($files as $file) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $newLines = [];
    
    $inBlock = false;
    $currentBlock = [];
    
    $seenPaths = [];
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        
        if (preg_match('/^\s*#\[OA\\\\([A-Za-z]+)\(/', $line, $matches)) {
            $inBlock = true;
            $currentBlock = [$line];
            $method = $matches[1];
            continue;
        }
        
        if ($inBlock) {
            $currentBlock[] = $line;
            if (preg_match('/^\s*\)]/', $line)) {
                $inBlock = false;
                
                // Block finished. Let's find the path.
                $path = '';
                foreach ($currentBlock as $bLine) {
                    if (preg_match('/path:\s*"([^"]+)"/', $bLine, $pMatch)) {
                        $path = $pMatch[1];
                        break;
                    }
                }
                
                $key = $method . '_' . $path;
                if (!isset($seenPaths[$key])) {
                    $seenPaths[$key] = true;
                    // Not a duplicate
                    foreach ($currentBlock as $bLine) {
                        $newLines[] = $bLine;
                    }
                } else {
                    // It's a duplicate, skip it!
                    echo "Removed duplicate $key in " . basename($file) . "\n";
                }
                
                $currentBlock = [];
            }
            continue;
        }
        
        // Not in block, but we might hit a function definition.
        // Once we hit a function, we clear seenPaths so that two different functions don't conflict
        // WAIT: Swagger does not allow the same method+path across the ENTIRE API!
        // But in the same controller, two different functions definitely shouldn't have the same method+path.
        // Actually, let's reset seenPaths per function? NO! Swagger requires method+path to be UNIQUE globally.
        // But what if the duplicate blocks are immediately adjacent?
        // Let's just keep seenPaths for the whole file, it's safer.
        
        $newLines[] = $line;
    }
    
    file_put_contents($file, implode("\n", $newLines));
}
