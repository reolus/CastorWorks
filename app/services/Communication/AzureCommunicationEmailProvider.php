<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Core\Env;
use RuntimeException;

final class AzureCommunicationEmailProvider implements ProviderInterface
{
    private AzureCommunicationClient $client;

    public function __construct()
    {
        $this->client = new AzureCommunicationClient();
    }

    public function key(): string { return 'azure_communication_email'; }
    public function channel(): string { return 'email'; }
    public function label(): string { return 'Azure Communication Services Email'; }
    public function configured(): bool
    {
        return $this->client->configured() && Env::string('AZURE_COMMUNICATION_EMAIL_FROM') !== '';
    }

    public function send(array $message): ProviderResult
    {
        if (!$this->configured()) {
            throw new RuntimeException('Azure Communication Services email is not configured.');
        }

        $attachments = [];
        foreach ((array) ($message['attachments'] ?? []) as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                continue;
            }
            $attachments[] = [
                'name' => (string) ($attachment['name'] ?? basename($path)),
                'contentType' => (string) ($attachment['type'] ?? 'application/octet-stream'),
                'contentInBase64' => base64_encode($bytes),
            ];
        }

        $apiVersion = Env::string('AZURE_COMMUNICATION_EMAIL_API_VERSION', '2023-03-31');
        $operationId = $this->uuid();
        $result = $this->client->request(
            'POST',
            '/emails:send?api-version=' . rawurlencode($apiVersion),
            [
                'senderAddress' => Env::string('AZURE_COMMUNICATION_EMAIL_FROM'),
                'recipients' => ['to' => [['address' => (string) ($message['to'] ?? '')]]],
                'content' => [
                    'subject' => (string) ($message['subject'] ?? ''),
                    'plainText' => trim(strip_tags((string) ($message['html'] ?? ''))),
                    'html' => (string) ($message['html'] ?? ''),
                ],
                'attachments' => $attachments,
            ],
            ['Operation-Id: ' . $operationId]
        );

        $messageId = (string) ($result['headers']['operation-location'] ?? $operationId);
        return new ProviderResult(true, $messageId, 'Azure Communication Services queued the email.', ['http_status' => $result['http_status']]);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
