<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Services\EmailService;
use App\Services\SmsService;
use App\Services\TemplateService;

require dirname(__DIR__) . '/app/core/Env.php';
Env::load(dirname(__DIR__) . '/.env');
require dirname(__DIR__) . '/vendor/autoload.php';

$pdo = Database::connection();
$campaigns = $pdo->query(
    "SELECT * FROM communication_campaigns
     WHERE status='scheduled' AND (scheduled_at IS NULL OR scheduled_at<=NOW())"
)->fetchAll();

foreach ($campaigns as $campaign) {
    $pdo->prepare("UPDATE communication_campaigns SET status='processing' WHERE id=?")
        ->execute([$campaign['id']]);

    $recipients = $pdo->prepare(
        "SELECT r.*,cu.display_name
         FROM communication_campaign_recipients r
         JOIN customers cu ON cu.id=r.customer_id
         WHERE r.campaign_id=? AND r.status='pending'"
    );
    $recipients->execute([$campaign['id']]);

    foreach ($recipients as $recipient) {
        try {
            $suppressed = $pdo->prepare(
                "SELECT COUNT(*) FROM marketing_suppressions
                 WHERE LOWER(destination)=LOWER(?) AND channel IN (?, 'all')"
            );
            $suppressed->execute([$recipient['destination'], $campaign['channel']]);
            if ((int) $suppressed->fetchColumn() > 0) {
                $pdo->prepare(
                    "UPDATE communication_campaign_recipients
                     SET status='skipped',error_message='Marketing suppression',processed_at=NOW()
                     WHERE id=?"
                )->execute([$recipient['id']]);
                continue;
            }

            $variables = ['customer_name' => $recipient['display_name']];
            if ($campaign['channel'] === 'email') {
                $mail = TemplateService::render($campaign['template_key'], $variables);
                (new EmailService())->sendMail(
                    $recipient['destination'],
                    $mail['subject'],
                    $mail['html_body']
                );
            } else {
                $template = $pdo->prepare(
                    'SELECT body FROM sms_templates WHERE template_key=? AND active=1'
                );
                $template->execute([$campaign['template_key']]);
                $body = (string) ($template->fetchColumn() ?: '');
                foreach ($variables as $key => $value) {
                    $body = str_replace('{{' . $key . '}}', (string) $value, $body);
                }
                SmsService::send(
                    (int) $recipient['customer_id'],
                    null,
                    $recipient['destination'],
                    $body
                );
            }

            $pdo->prepare(
                "UPDATE communication_campaign_recipients
                 SET status='sent',processed_at=NOW() WHERE id=?"
            )->execute([$recipient['id']]);
        } catch (Throwable $e) {
            $pdo->prepare(
                "UPDATE communication_campaign_recipients
                 SET status='failed',error_message=?,processed_at=NOW() WHERE id=?"
            )->execute([$e->getMessage(), $recipient['id']]);
        }
    }

    $pdo->prepare("UPDATE communication_campaigns SET status='completed' WHERE id=?")
        ->execute([$campaign['id']]);
}
