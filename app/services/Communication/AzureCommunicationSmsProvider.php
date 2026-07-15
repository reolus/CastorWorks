<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Core\Env;
use RuntimeException;

final class AzureCommunicationSmsProvider implements ProviderInterface
{
    private AzureCommunicationClient $client;

    public function __construct()
    {
        $this->client = new AzureCommunicationClient();
    }

    public function key(): string { return 'azure_communication_sms'; }
    public function channel(): string { return 'sms'; }
    public function label(): string { return 'Azure Communication Services SMS'; }
    public function configured(): bool
    {
        return $this->client->configured() && Env::string('AZURE_COMMUNICATION_SMS_FROM') !== '';
    }

    public function send(array $message): ProviderResult
    {
        if (!$this->configured()) {
            throw new RuntimeException('Azure Communication Services SMS is not configured.');
        }

        $apiVersion = Env::string('AZURE_COMMUNICATION_SMS_API_VERSION', '2021-03-07');
        $result = $this->client->request('POST', '/sms?api-version=' . rawurlencode($apiVersion), [
            'from' => Env::string('AZURE_COMMUNICATION_SMS_FROM'),
            'smsRecipients' => [['to' => (string) ($message['to'] ?? '')]],
            'message' => (string) ($message['text'] ?? ''),
            'smsSendOptions' => [
                'enableDeliveryReport' => true,
                'tag' => substr((string) (($message['context']['event_key'] ?? 'serviceos')), 0, 64),
            ],
        ]);

        $responses = $result['body']['smsSendResults'] ?? [];
        $first = is_array($responses) ? ($responses[0] ?? []) : [];
        if (is_array($first) && isset($first['successful']) && !$first['successful']) {
            throw new RuntimeException((string) ($first['httpStatusCode'] ?? 'Azure SMS send failed') . ': ' . (string) ($first['errorMessage'] ?? 'Unknown error'));
        }

        return new ProviderResult(
            true,
            is_array($first) ? (string) ($first['messageId'] ?? '') : null,
            'Azure Communication Services accepted the SMS.',
            ['http_status' => $result['http_status']]
        );
    }
}
