<?php

declare(strict_types=1);

namespace App\Services\Communication;

final class ProviderResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $detail = null,
        public readonly array $metadata = [],
    ) {
    }
}
