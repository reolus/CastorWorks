<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Core\Env;
use RuntimeException;

final class AmazonSnsSmsProvider implements ProviderInterface
{
    public function key(): string { return 'amazon_sns_sms'; }
    public function channel(): string { return 'sms'; }
    public function label(): string { return 'Amazon SNS SMS'; }
    public function configured(): bool { return AwsClientFactory::configured(); }

    public function send(array $message): ProviderResult
    {
        if (!$this->configured()) {
            throw new RuntimeException('Amazon SNS SMS is not configured.');
        }

        $attributes = [
            'AWS.SNS.SMS.SMSType' => [
                'DataType' => 'String',
                'StringValue' => Env::string('AWS_SNS_SMS_TYPE', 'Transactional'),
            ],
        ];
        $senderId = Env::string('AWS_SNS_SMS_SENDER_ID');
        if ($senderId !== '') {
            $attributes['AWS.SNS.SMS.SenderID'] = ['DataType' => 'String', 'StringValue' => $senderId];
        }

        $client = new \Aws\Sns\SnsClient(AwsClientFactory::config());
        $result = $client->publish([
            'PhoneNumber' => (string) ($message['to'] ?? ''),
            'Message' => (string) ($message['text'] ?? ''),
            'MessageAttributes' => $attributes,
        ])->toArray();

        return new ProviderResult(true, (string) ($result['MessageId'] ?? ''), 'Amazon SNS accepted the SMS.');
    }
}
