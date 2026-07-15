<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Core\Env;
use RuntimeException;

final class AwsEndUserMessagingSmsProvider implements ProviderInterface
{
    public function key(): string { return 'aws_end_user_messaging_sms'; }
    public function channel(): string { return 'sms'; }
    public function label(): string { return 'AWS End User Messaging SMS'; }
    public function configured(): bool
    {
        return AwsClientFactory::configured()
            && Env::string('AWS_EUM_ORIGINATION_IDENTITY') !== '';
    }

    public function send(array $message): ProviderResult
    {
        if (!$this->configured()) {
            throw new RuntimeException('AWS End User Messaging SMS is not configured.');
        }

        $client = new \Aws\PinpointSMSVoiceV2\PinpointSMSVoiceV2Client(AwsClientFactory::config());
        $params = [
            'DestinationPhoneNumber' => (string) ($message['to'] ?? ''),
            'MessageBody' => (string) ($message['text'] ?? ''),
            'MessageType' => strtoupper(Env::string('AWS_EUM_MESSAGE_TYPE', 'TRANSACTIONAL')),
            'OriginationIdentity' => Env::string('AWS_EUM_ORIGINATION_IDENTITY'),
        ];

        $configurationSet = Env::string('AWS_EUM_CONFIGURATION_SET');
        if ($configurationSet !== '') {
            $params['ConfigurationSetName'] = $configurationSet;
        }

        $context = $message['context'] ?? [];
        if (is_array($context) && $context !== []) {
            $params['Context'] = array_map('strval', $context);
        }

        $result = $client->sendTextMessage($params)->toArray();
        return new ProviderResult(
            true,
            (string) ($result['MessageId'] ?? ''),
            'AWS End User Messaging accepted the SMS.',
            ['response_metadata' => $result['@metadata'] ?? []]
        );
    }
}
