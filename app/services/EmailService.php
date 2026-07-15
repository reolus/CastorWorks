<?php

declare(strict_types=1);

namespace App\Services;

final class EmailService
{
    public function configured(): bool
    {
        foreach ((new CommunicationManager())->status() as $provider) {
            if ($provider['channel'] === 'email' && (int) $provider['enabled'] === 1 && $provider['configured']) {
                return true;
            }
        }
        return false;
    }

    public function sendMail(string $to, string $subject, string $html, array $attachments = [], array $context = []): array
    {
        return (new CommunicationManager())->sendEmail($to, $subject, $html, $attachments, $context);
    }
}
