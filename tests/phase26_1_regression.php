<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$checks = [
    'app/controllers/EstimateController.php' => [
        "reference_type='estimate'",
        'reference_id=?',
    ],
    'app/controllers/InvoiceController.php' => [
        "reference_type='invoice'",
        'reference_id=?',
    ],
    'app/services/DocumentService.php' => [
        'INSERT INTO documents(reference_type,reference_id,document_type,original_name',
    ],
    'app/controllers/DocumentController.php' => [
        "\$doc['original_name']",
    ],
    'app/services/GraphService.php' => [
        "if (isset(\$attachments['path']))",
        'if(!is_array($a)) continue;',
    ],
    'database/migrate_phase26_1.sql' => [
        'CHANGE entity_type reference_type',
        'CHANGE entity_id reference_id',
        'CHANGE filename original_name',
        'CHANGE created_at received_at',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    $content = is_file($path) ? file_get_contents($path) : false;
    if ($content === false) {
        $failures[] = "Missing file: {$file}";
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $failures[] = "Missing expected content in {$file}: {$needle}";
        }
    }
}

$legacyChecks = [
    'app/controllers/EstimateController.php' => ["documents WHERE entity_type"],
    'app/controllers/InvoiceController.php' => ["documents WHERE entity_type"],
    'app/services/DocumentService.php' => ['INSERT INTO documents(entity_type'],
    'app/controllers/DocumentController.php' => ["\$doc['filename']"],
    'app/views/portal/estimates/show.php' => ["\$doc['filename']"],
    'app/views/portal/invoices/show.php' => ["\$doc['filename']"],
];

foreach ($legacyChecks as $file => $legacyNeedles) {
    $content = file_get_contents($root . '/' . $file) ?: '';
    foreach ($legacyNeedles as $legacy) {
        if (str_contains($content, $legacy)) {
            $failures[] = "Legacy document-column reference remains in {$file}: {$legacy}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "ServiceOS 0.26.1 regression checks passed.\n";
