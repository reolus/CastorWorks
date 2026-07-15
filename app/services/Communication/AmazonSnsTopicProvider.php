<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Core\Env;
use RuntimeException;

final class AmazonSnsTopicProvider implements ProviderInterface
{
    public function key(): string { return 'amazon_sns_topic'; }
    public function channel(): string { return 'topic'; }
    public function label(): string { return 'Amazon SNS Topic'; }
    public function configured(): bool
    {
        return AwsClientFactory::configured() && Env::string('AWS_SNS_TOPIC_ARN') !== '';
    }

    public function send(array $message): ProviderResult
    {
        if (!$this->configured()) {
            throw new RuntimeException('Amazon SNS topic publishing is not configured.');
        }

        $client = new \Aws\Sns\SnsClient(AwsClientFactory::config());
        $result = $client->publish([
            'TopicArn' => (string) ($message['topic_arn'] ?? Env::string('AWS_SNS_TOPIC_ARN')),
            'Subject' => substr((string) ($message['subject'] ?? 'ServiceOS notification'), 0, 100),
            'Message' => (string) ($message['text'] ?? ''),
            'MessageAttributes' => $this->attributes((array) ($message['context'] ?? [])),
        ])->toArray();

        return new ProviderResult(true, (string) ($result['MessageId'] ?? ''), 'Amazon SNS accepted the topic message.');
    }

    private function attributes(array $context): array
    {
        $attributes = [];
        foreach ($context as $key => $value) {
            if (!is_scalar($value) || count($attributes) >= 10) {
                continue;
            }
            $attributes[(string) $key] = ['DataType' => 'String', 'StringValue' => (string) $value];
        }
        return $attributes;
    }
}
