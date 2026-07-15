<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$assertContains = static function (string $needle, string $haystack, string $message) use (&$failures): void {
    if (!str_contains($haystack, $needle)) {
        $failures[] = $message;
    }
};

$migration = file_get_contents($root . '/database/migrate_phase29_1.sql') ?: '';
$schemaCheck = file_get_contents($root . '/scripts/schema-check.php') ?: '';
$mobile = file_get_contents($root . '/app/controllers/MobileController.php') ?: '';
$invoice = file_get_contents($root . '/app/controllers/InvoiceController.php') ?: '';

$assertContains('COLUMN_NAME = \'job_id\'', $migration, 'Migration does not conditionally check invoices.job_id.');
$assertContains('ADD COLUMN job_id BIGINT UNSIGNED NULL', $migration, 'Migration does not add invoices.job_id.');
$assertContains('idx_invoices_job_id', $migration, 'Migration does not add the invoice job index.');
$assertContains('fk_invoices_job', $migration, 'Migration does not add the invoice job foreign key.');
$assertContains("'invoices' => ['customer_id', 'job_id']", $schemaCheck, 'Schema checker does not validate invoices.job_id.');
$assertContains('FROM invoices WHERE job_id=?', $mobile, 'Mobile workflow no longer queries invoices by job_id.');
$assertContains('INSERT INTO invoices(invoice_number,public_token,customer_id,job_id', $invoice, 'Invoice creation does not populate job_id.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "ServiceOS 0.29.1 regression checks passed.\n";
