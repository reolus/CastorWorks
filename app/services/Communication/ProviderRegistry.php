<?php

declare(strict_types=1);

namespace App\Services\Communication;

final class ProviderRegistry
{
    /** @return array<string,ProviderInterface> */
    public function all(): array
    {
        $providers = [
            new MicrosoftGraphEmailProvider(),
            new AmazonSesEmailProvider(),
            new AzureCommunicationEmailProvider(),
            new AwsEndUserMessagingSmsProvider(),
            new AmazonSnsSmsProvider(),
            new AzureCommunicationSmsProvider(),
            new TwilioSmsProvider(),
            new AmazonSnsTopicProvider(),
        ];

        $indexed = [];
        foreach ($providers as $provider) {
            $indexed[$provider->key()] = $provider;
        }
        return $indexed;
    }

    public function get(string $key): ?ProviderInterface
    {
        return $this->all()[$key] ?? null;
    }
}
