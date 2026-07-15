<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use PDO;
use RuntimeException;
use Throwable;

final class AiProviderService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        try {
            $row = $this->pdo->query('SELECT * FROM ai_provider_settings WHERE id = 1')->fetch();
            if (is_array($row)) {
                return $row;
            }
        } catch (Throwable) {
            // The health page must survive a pending migration.
        }

        return [
            'enabled' => 0,
            'provider' => 'disabled',
            'model' => '',
            'endpoint' => '',
            'temperature' => 0.2,
            'max_tokens' => 800,
            'daily_request_limit' => 0,
            'monthly_request_limit' => 0,
            'allowed_roles' => 'owner,administrator,office,estimator',
            'redact_sensitive_data' => 1,
            'system_prompt' => 'You are the CastorWorks operations assistant. Be factual, concise, and identify uncertainty.',
        ];
    }

    /** @return array{name:string,status:string,detail:string} */
    public function healthCheck(): array
    {
        $settings = $this->settings();
        if (!(bool) ($settings['enabled'] ?? false) || ($settings['provider'] ?? 'disabled') === 'disabled') {
            return ['name' => 'AI Assistant', 'status' => 'disabled', 'detail' => 'Disabled'];
        }

        $provider = (string) $settings['provider'];
        $configured = match ($provider) {
            'openai' => Env::get('OPENAI_API_KEY', '') !== '',
            'azure_openai' => Env::get('AZURE_OPENAI_API_KEY', '') !== ''
                && Env::get('AZURE_OPENAI_ENDPOINT', '') !== ''
                && Env::get('AZURE_OPENAI_DEPLOYMENT', '') !== '',
            'ollama' => (string) ($settings['endpoint'] ?: Env::get('OLLAMA_ENDPOINT', 'http://127.0.0.1:11434')) !== '',
            default => false,
        };

        return [
            'name' => 'AI Assistant',
            'status' => $configured ? 'ok' : 'warning',
            'detail' => $configured
                ? ucfirst(str_replace('_', ' ', $provider)) . ' configured'
                : ucfirst(str_replace('_', ' ', $provider)) . ' is enabled but missing required configuration',
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array{content:string,provider:string,model:string,latency_ms:int,input_chars:int,output_chars:int}
     */
    public function ask(string $prompt, string $purpose = 'assistant', array $context = []): array
    {
        $settings = $this->settings();
        if (!(bool) ($settings['enabled'] ?? false)) {
            throw new RuntimeException('The AI Assistant is disabled.');
        }

        $provider = (string) ($settings['provider'] ?? 'disabled');
        $model = trim((string) ($settings['model'] ?? ''));
        if ($provider === 'disabled') {
            throw new RuntimeException('No AI provider is selected.');
        }

        $this->enforceUsageLimits($settings);
        $system = trim((string) ($settings['system_prompt'] ?? ''));
        $contextText = $context === [] ? '' : "\n\nOperational context (aggregate data only):\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ((bool) ($settings['redact_sensitive_data'] ?? true)) {
            $policy = new AiPolicyService($this->pdo);
            $prompt = $policy->redact($prompt);
            $contextText = $policy->redact($contextText);
        }
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt . $contextText],
        ];

        $started = microtime(true);
        try {
            $content = match ($provider) {
                'openai' => $this->openAi($messages, $model, $settings),
                'azure_openai' => $this->azureOpenAi($messages, $model, $settings),
                'ollama' => $this->ollama($messages, $model, $settings),
                default => throw new RuntimeException('Unsupported AI provider: ' . $provider),
            };
        } catch (Throwable $e) {
            try {
                $this->pdo->prepare("INSERT INTO ai_usage_logs(user_id,provider,model,purpose,prompt_hash,input_chars,output_chars,estimated_cost_usd,status,latency_ms,error_message,created_at) VALUES(NULL,?,?,?,?,?,0,0,'failed',?,?,NOW())")
                    ->execute([$provider, $model, $purpose, hash('sha256', $prompt), strlen($prompt . $contextText), (int) round((microtime(true) - $started) * 1000), mb_substr($e->getMessage(), 0, 2000)]);
            } catch (Throwable) {
            }
            throw $e;
        }

        return [
            'content' => $content,
            'provider' => $provider,
            'model' => $model,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'input_chars' => strlen($prompt . $contextText),
            'output_chars' => strlen($content),
        ];
    }


    /** @return array{name:string,status:string,detail:string,latency_ms:int} */
    public function testConnection(): array
    {
        $started = microtime(true);
        $result = $this->ask('Reply with exactly: CASTORWORKS_AI_OK', 'provider_test');
        $ok = str_contains(strtoupper((string) $result['content']), 'CASTORWORKS_AI_OK');
        if (!$ok) {
            throw new RuntimeException('The provider responded, but the validation phrase was not returned.');
        }
        return [
            'name' => 'AI Assistant',
            'status' => 'ok',
            'detail' => ucfirst(str_replace('_', ' ', (string) $result['provider'])) . ' responded successfully',
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /** @param array<string,mixed> $settings */
    private function enforceUsageLimits(array $settings): void
    {
        $daily = (int) ($settings['daily_request_limit'] ?? 0);
        $monthly = (int) ($settings['monthly_request_limit'] ?? 0);
        try {
            if ($daily > 0) {
                $count = (int) $this->pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE status='success' AND created_at >= CURDATE()")->fetchColumn();
                if ($count >= $daily) {
                    throw new RuntimeException('The daily AI request limit has been reached.');
                }
            }
            if ($monthly > 0) {
                $count = (int) $this->pdo->query("SELECT COUNT(*) FROM ai_usage_logs WHERE status='success' AND created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
                if ($count >= $monthly) {
                    throw new RuntimeException('The monthly AI request limit has been reached.');
                }
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable) {
            // Pending migrations should not take the entire assistant offline.
        }
    }

    /** @param list<array{role:string,content:string}> $messages */
    private function openAi(array $messages, string $model, array $settings): string
    {
        $endpoint = rtrim((string) ($settings['endpoint'] ?: 'https://api.openai.com'), '/');
        $payload = [
            'model' => $model !== '' ? $model : 'gpt-4.1-mini',
            'messages' => $messages,
            'temperature' => (float) $settings['temperature'],
            'max_tokens' => (int) $settings['max_tokens'],
        ];
        $response = $this->request($endpoint . '/v1/chat/completions', $payload, [
            'Authorization: Bearer ' . Env::get('OPENAI_API_KEY', ''),
        ]);
        return $this->extractChatContent($response);
    }

    /** @param list<array{role:string,content:string}> $messages */
    private function azureOpenAi(array $messages, string $model, array $settings): string
    {
        $endpoint = rtrim((string) Env::get('AZURE_OPENAI_ENDPOINT', ''), '/');
        $deployment = rawurlencode((string) Env::get('AZURE_OPENAI_DEPLOYMENT', $model));
        $apiVersion = rawurlencode((string) Env::get('AZURE_OPENAI_API_VERSION', '2024-10-21'));
        $payload = [
            'messages' => $messages,
            'temperature' => (float) $settings['temperature'],
            'max_tokens' => (int) $settings['max_tokens'],
        ];
        $response = $this->request(
            $endpoint . '/openai/deployments/' . $deployment . '/chat/completions?api-version=' . $apiVersion,
            $payload,
            ['api-key: ' . Env::get('AZURE_OPENAI_API_KEY', '')]
        );
        return $this->extractChatContent($response);
    }

    /** @param list<array{role:string,content:string}> $messages */
    private function ollama(array $messages, string $model, array $settings): string
    {
        $endpoint = rtrim((string) ($settings['endpoint'] ?: Env::get('OLLAMA_ENDPOINT', 'http://127.0.0.1:11434')), '/');
        $response = $this->request($endpoint . '/api/chat', [
            'model' => $model !== '' ? $model : 'llama3.2',
            'messages' => $messages,
            'stream' => false,
            'options' => ['temperature' => (float) $settings['temperature']],
        ]);
        $content = $response['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Ollama returned no assistant content.');
        }
        return trim($content);
    }

    /** @return array<string,mixed> */
    private function request(string $url, array $payload, array $headers = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required for AI providers.');
        }

        $curl = curl_init($url);
        $headers[] = 'Content-Type: application/json';
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(10, min(300, (int) ((new AiPolicyService($this->pdo))->settings()['provider_timeout_seconds'] ?? 90))),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('AI provider request failed: ' . $error);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('AI provider returned an invalid JSON response.');
        }
        if ($status < 200 || $status >= 300) {
            $message = $decoded['error']['message'] ?? $decoded['message'] ?? ('HTTP ' . $status);
            throw new RuntimeException('AI provider error: ' . (string) $message);
        }
        return $decoded;
    }

    /** @param array<string,mixed> $response */
    private function extractChatContent(array $response): string
    {
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI provider returned no assistant content.');
        }
        return trim($content);
    }
}
