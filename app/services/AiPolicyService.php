<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AiPolicyService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        $row = $this->pdo->query('SELECT * FROM ai_policy_settings WHERE id=1')->fetch();
        return is_array($row) ? $row : [
            'draft_retention_days' => 90,
            'usage_retention_days' => 365,
            'audit_retention_days' => 730,
            'redact_email' => 1,
            'redact_phone' => 1,
            'redact_address' => 1,
            'redact_customer_names' => 0,
            'custom_redaction_patterns' => '',
            'draft_expiration_days' => 30,
            'provider_timeout_seconds' => 90,
        ];
    }

    public function redact(string $text): string
    {
        $settings = $this->settings();
        if ((int) ($settings['redact_email'] ?? 1) === 1) {
            $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[REDACTED EMAIL]', $text) ?? $text;
        }
        if ((int) ($settings['redact_phone'] ?? 1) === 1) {
            $text = preg_replace('/(?<!\d)(?:\+?1[\s.\-]?)?(?:\(?\d{3}\)?[\s.\-]?)\d{3}[\s.\-]?\d{4}(?!\d)/', '[REDACTED PHONE]', $text) ?? $text;
        }
        if ((int) ($settings['redact_address'] ?? 1) === 1) {
            $text = preg_replace('/\b\d{1,6}\s+[A-Za-z0-9.\-\s]+\s(?:Street|St|Road|Rd|Avenue|Ave|Drive|Dr|Lane|Ln|Boulevard|Blvd|Court|Ct|Highway|Hwy)\b\.?/i', '[REDACTED ADDRESS]', $text) ?? $text;
        }

        $patterns = preg_split('/\R+/', trim((string) ($settings['custom_redaction_patterns'] ?? ''))) ?: [];
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                continue;
            }
            $candidate = @preg_replace($pattern, '[REDACTED]', $text);
            if (is_string($candidate)) {
                $text = $candidate;
            }
        }
        return $text;
    }
}
