<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'app/services/PhoneNumberService.php' => ['smsParts', 'normalize'],
    'app/services/CommunicationConsentService.php' => ['marketing_suppressions'],
    'app/services/CommunicationReceiptService.php' => ['communication_receipts', 'communication_inbound_messages'],
    'app/controllers/CommunicationWebhookController.php' => ['SubscriptionValidationEvent', 'CommunicationReceiptService'],
    'app/services/CommunicationReceiptService.php' => ['STOPALL', 'communication_inbound_messages'],
    'app/services/CommunicationManager.php' => ['allow_transactional', 'allow_marketing', 'daily_limit', 'monthly_limit'],
    'database/migrate_phase31.sql' => ['communication_receipts', 'communication_inbound_messages', 'delivery_status'],
    'public/index.php' => ['/webhooks/communications/aws', '/webhooks/communications/azure', '/webhooks/communications/twilio'],
];
$failed = false;
foreach ($checks as $file => $needles) {
    $content = file_get_contents($root . '/' . $file);
    if ($content === false) { echo "Missing {$file}\n"; $failed = true; continue; }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) { echo "Missing {$needle} in {$file}\n"; $failed = true; }
    }
}
require_once $root . '/app/services/PhoneNumberService.php';
$parts = App\Services\PhoneNumberService::smsParts(str_repeat('A', 161));
if (($parts['parts'] ?? 0) !== 2) { echo "SMS segmentation failed\n"; $failed = true; }
if (App\Services\PhoneNumberService::normalize('(402) 555-1212') !== '+14025551212') { echo "Phone normalization failed\n"; $failed = true; }
exit($failed ? 1 : 0);
