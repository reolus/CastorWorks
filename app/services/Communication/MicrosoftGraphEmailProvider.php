<?php

declare(strict_types=1);

namespace App\Services\Communication;

use App\Services\GraphService;
use RuntimeException;

final class MicrosoftGraphEmailProvider implements ProviderInterface
{
    public function key(): string { return 'microsoft_graph_email'; }
    public function channel(): string { return 'email'; }
    public function label(): string { return 'Microsoft Graph Email'; }
    public function configured(): bool { return (new GraphService())->configured(); }

    public function send(array $message): ProviderResult
    {
        $graph = new GraphService();
        if (!$graph->configured()) {
            throw new RuntimeException('Microsoft Graph email is not configured.');
        }

        $graph->sendMail(
            (string) ($message['to'] ?? ''),
            (string) ($message['subject'] ?? ''),
            (string) ($message['html'] ?? ''),
            (array) ($message['attachments'] ?? [])
        );

        return new ProviderResult(true, null, 'Microsoft Graph accepted the email.');
    }
}
