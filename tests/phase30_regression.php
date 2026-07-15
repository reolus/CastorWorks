<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = [
    'app/services/CommunicationManager.php' => ['providersForChannel', 'COMMUNICATION_FALLBACK_ENABLED', 'communication_delivery_attempts'],
    'app/services/SmsService.php' => ['CommunicationManager', 'provider_key'],
    'app/services/EmailService.php' => ['sendEmail'],
    'app/controllers/CommunicationProviderController.php' => ['Communication Providers', 'ProviderRegistry'],
    'database/migrate_phase30.sql' => ['communication_providers', 'aws_end_user_messaging_sms', 'amazon_ses_email', 'provider_key'],
    'app/services/Communication/AwsEndUserMessagingSmsProvider.php' => ['PinpointSMSVoiceV2Client', 'sendTextMessage'],
    'app/services/Communication/AmazonSnsSmsProvider.php' => ['SnsClient', 'PhoneNumber'],
    'app/services/Communication/AzureCommunicationSmsProvider.php' => ['AzureCommunicationClient', '/sms?api-version='],
    'app/services/Communication/MicrosoftGraphEmailProvider.php' => ['GraphService'],
    'app/services/Communication/AmazonSesEmailProvider.php' => ['SesV2Client'],
];

foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $failures[] = "Missing {$relative}";
        continue;
    }
    $content = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $failures[] = "{$relative} does not contain {$needle}";
        }
    }
}

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);

$awsRequirement = $composer['require']['aws/aws-sdk-php'] ?? null;

if (!is_string($awsRequirement) || $awsRequirement === '') {
    $errors[] = 'composer.json does not require aws/aws-sdk-php';
}


$legacyDirectMail = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/controllers'));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $content = (string) file_get_contents($file->getPathname());
    if (preg_match('/new\s+GraphService\s*\(\s*\)\s*->\s*sendMail/', $content)) {
        $legacyDirectMail[] = $file->getPathname();
    }
}
if ($legacyDirectMail !== []) {
    $failures[] = 'Controller mail still bypasses EmailService: ' . implode(', ', $legacyDirectMail);
}

if ($failures !== []) {
    fwrite(STDERR, "ServiceOS 0.30.0 regression failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "ServiceOS 0.30.0 communication-provider regression passed.\n";
