<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Services\CommunicationReceiptService;

final class CommunicationWebhookController
{
    public function aws(): void
    {
        $this->authorize();
        $payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $message = $payload;
        if (isset($payload['Message']) && is_string($payload['Message'])) {
            $message = json_decode($payload['Message'], true) ?: ['message' => $payload['Message']];
        }
        $id = (string) ($message['messageId'] ?? $message['message_id'] ?? $payload['MessageId'] ?? '');
        $status = (string) ($message['delivery']['status'] ?? $message['status'] ?? $message['eventType'] ?? 'unknown');
        $destination = (string) ($message['destination'] ?? $message['phoneNumber'] ?? '');
        (new CommunicationReceiptService())->record('aws_end_user_messaging_sms', $id ?: null, $status, $payload, $destination ?: null);
        $this->ok();
    }

    public function azure(): void
    {
        $this->authorize();
        $payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $events = array_is_list($payload) ? $payload : [$payload];
        foreach ($events as $event) {
            if (($event['eventType'] ?? '') === 'Microsoft.EventGrid.SubscriptionValidationEvent') {
                header('Content-Type: application/json');
                echo json_encode(['validationResponse' => $event['data']['validationCode'] ?? '']);
                return;
            }
            $data = (array) ($event['data'] ?? []);
            (new CommunicationReceiptService())->record(
                str_contains(strtolower((string) ($event['eventType'] ?? '')), 'email') ? 'azure_communication_email' : 'azure_communication_sms',
                (string) ($data['messageId'] ?? '') ?: null,
                (string) ($data['deliveryStatus'] ?? $data['status'] ?? 'unknown'),
                $event,
                (string) ($data['to'] ?? $data['recipient'] ?? '') ?: null
            );
        }
        $this->ok();
    }

    public function twilio(): void
    {
        $this->authorize();
        $data = $_POST;
        $service = new CommunicationReceiptService();
        if (isset($data['Body']) && isset($data['From'])) {
            $service->inbound('twilio_sms', (string) $data['From'], (string) ($data['To'] ?? ''), (string) $data['Body'], $data);
        } else {
            $service->record('twilio_sms', (string) ($data['MessageSid'] ?? '') ?: null, (string) ($data['MessageStatus'] ?? 'unknown'), $data, (string) ($data['To'] ?? '') ?: null);
        }
        $this->ok();
    }

    private function authorize(): void
    {
        $secret = Env::string('COMMUNICATION_WEBHOOK_SECRET');
        if ($secret === '') {
            return;
        }
        $provided = (string) ($_SERVER['HTTP_X_SERVICEOS_WEBHOOK_SECRET'] ?? $_GET['secret'] ?? '');
        if (!hash_equals($secret, $provided)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    private function ok(): void
    {
        http_response_code(202);
        header('Content-Type: application/json');
        echo json_encode(['accepted' => true]);
    }
}
