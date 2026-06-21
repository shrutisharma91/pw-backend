<?php
$swagger = json_decode(file_get_contents('storage/api-docs/api-docs.json'), true);
foreach (array_keys($swagger['paths'] ?? []) as $p) echo $p . "\n";
