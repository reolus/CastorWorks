<?php

declare(strict_types=1);

namespace App\Services;

final class AiCostService
{
    /**
     * Character counts are converted to approximate tokens at four characters per token.
     * This is an estimate for governance reporting, not a provider invoice.
     */
    public function estimate(int $inputCharacters, int $outputCharacters, array $settings): float
    {
        $inputTokens = max(0, $inputCharacters) / 4;
        $outputTokens = max(0, $outputCharacters) / 4;
        $inputRate = max(0, (float) ($settings['input_cost_per_million_tokens'] ?? 0));
        $outputRate = max(0, (float) ($settings['output_cost_per_million_tokens'] ?? 0));
        return round(($inputTokens / 1_000_000 * $inputRate) + ($outputTokens / 1_000_000 * $outputRate), 6);
    }
}
