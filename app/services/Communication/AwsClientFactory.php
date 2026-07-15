<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Core\Env;
use RuntimeException;

final class AwsClientFactory
{
    public static function config(): array
    {
        if (!class_exists(\Aws\Sdk::class)) {
            throw new RuntimeException('AWS SDK for PHP is not installed. Run composer install.');
        }

        $region = Env::string('AWS_REGION', 'us-east-1');
        $config = [
            'region' => $region,
            'version' => 'latest',
        ];

        $key = Env::string('AWS_ACCESS_KEY_ID');
        $secret = Env::string('AWS_SECRET_ACCESS_KEY');
        $token = Env::string('AWS_SESSION_TOKEN');

        if ($key !== '' && $secret !== '') {
            $config['credentials'] = [
                'key' => $key,
                'secret' => $secret,
                'token' => $token !== '' ? $token : null,
            ];
        }

        return $config;
    }

    public static function configured(): bool
    {
        // Explicit credentials are optional when the host uses an IAM role.
        return Env::string('AWS_REGION', '') !== ''
            && (class_exists(\Aws\Sdk::class));
    }
}
