<?php

declare(strict_types=1);

namespace App\Services;

final class AiRedactionService
{
    /** @param array<string,mixed> $value @return array<string,mixed> */
    public function redactArray(array $value): array
    {
        $sensitiveKeys = ['email','phone','mobile','address','street','postal_code','zip','notes','message','body','content'];
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $value[$key] = '[redacted]';
                continue;
            }
            if (is_array($item)) {
                $value[$key] = $this->redactArray($item);
            }
        }
        return $value;
    }

    public function redactText(string $text): string
    {
        $text = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[redacted-email]', $text) ?? $text;
        $text = preg_replace('/\+?\d[\d\s().-]{7,}\d/', '[redacted-phone]', $text) ?? $text;
        return $text;
    }
}
