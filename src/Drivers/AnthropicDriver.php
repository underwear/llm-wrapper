<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Underwear\LlmWrapper\ChatBuilder\ChatBuilder;
use Underwear\LlmWrapper\Exceptions\LlmApiException;
use Underwear\LlmWrapper\Exceptions\LlmConfigurationException;
use Underwear\LlmWrapper\LlmDriverInterface;
use Underwear\LlmWrapper\LlmResponse\LlmResponse;
use Underwear\LlmWrapper\LlmResponse\Usage;
use Underwear\LlmWrapper\LlmResponse\ToolCall;

class AnthropicDriver implements LlmDriverInterface
{
    private const API_BASE_URL = 'https://api.anthropic.com/v1';
    private const DEFAULT_MODEL = 'claude-sonnet-4-20250514';
    private const API_VERSION = '2023-06-01';
    private const TIMEOUT = 60.0;
    private const MAX_TOKENS_DEFAULT = 4096;

    private array $config = [];
    private ?string $defaultModel = null;
    private ?ClientInterface $httpClient = null;

    public function setDefaultModel(string $model): void
    {
        $this->defaultModel = $model;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function setHttpClient(ClientInterface $client): void
    {
        $this->httpClient = $client;
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function sendRequest(ChatBuilder $chatBuilder): LlmResponse
    {
        $payload = $this->buildPayload($chatBuilder);
        $response = $this->makeHttpRequest($payload);

        return $this->parseResponse($response);
    }

    private function buildPayload(ChatBuilder $chatBuilder): array
    {
        $chatArray = $chatBuilder->toArray();

        $model = $chatArray['model'] ?? $this->defaultModel ?? self::DEFAULT_MODEL;

        $messages = $this->filterNonSystemMessages($chatArray['messages']);
        $systemMessage = $this->extractSystemMessage($chatArray['messages']);

        $maxTokens = $chatArray['max_tokens'] ?? $this->config['max_tokens'] ?? self::MAX_TOKENS_DEFAULT;

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];

        if ($systemMessage !== null) {
            $payload['system'] = $systemMessage;
        }

        if (isset($chatArray['temperature'])) {
            $payload['temperature'] = $chatArray['temperature'];
        }

        if (isset($chatArray['tools']) && !empty($chatArray['tools'])) {
            $payload['tools'] = $this->transformTools($chatArray['tools']);
        }

        if (isset($chatArray['tool_choice'])) {
            $toolChoice = $this->transformToolChoice($chatArray['tool_choice']);
            if ($toolChoice !== null) {
                $payload['tool_choice'] = $toolChoice;
            }
        }

        return $payload;
    }

    private function filterNonSystemMessages(array $messages): array
    {
        $filtered = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                continue;
            }

            $filtered[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        return $filtered;
    }

    private function extractSystemMessage(array $messages): ?string
    {
        $systemParts = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemParts[] = $message['content'];
            }
        }

        return !empty($systemParts) ? implode("\n\n", $systemParts) : null;
    }

    private function transformTools(array $tools): array
    {
        $result = [];

        foreach ($tools as $tool) {
            $result[] = [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'input_schema' => $tool['parameters'] ?? [
                    'type' => 'object',
                    'properties' => [],
                ],
            ];
        }

        return $result;
    }

    private function transformToolChoice(string|array $toolChoice): ?array
    {
        if (is_array($toolChoice) && isset($toolChoice['name'])) {
            return ['type' => 'tool', 'name' => $toolChoice['name']];
        }

        return match ($toolChoice) {
            'auto' => ['type' => 'auto'],
            'required' => ['type' => 'any'],
            'none' => null,
            default => ['type' => 'auto'],
        };
    }

    private function makeHttpRequest(array $payload): ResponseInterface
    {
        $client = $this->getHttpClient();
        $apiKey = $this->getApiKey();

        try {
            return $client->post(self::API_BASE_URL . '/messages', [
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => self::TIMEOUT,
            ]);
        } catch (RequestException $e) {
            throw new LlmApiException(
                'Anthropic API request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    private function parseResponse(ResponseInterface $response): LlmResponse
    {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LlmApiException('Invalid JSON response from Anthropic API');
        }

        if (isset($data['error'])) {
            throw new LlmApiException(
                'Anthropic API Error: ' . ($data['error']['message'] ?? 'Unknown error'),
                $data['error']['type'] ?? 0
            );
        }

        if (!isset($data['content']) || empty($data['content'])) {
            throw new LlmApiException('No content returned from Anthropic API');
        }

        $content = '';
        $toolCalls = [];

        foreach ($data['content'] as $contentBlock) {
            if ($contentBlock['type'] === 'text') {
                $content .= $contentBlock['text'];
            } elseif ($contentBlock['type'] === 'tool_use') {
                $name = $contentBlock['name'];
                $arguments = $contentBlock['input'] ?? [];
                $toolCalls[$name] = new ToolCall($name, $arguments);
            }
        }

        $usage = new Usage(
            promptTokens: $data['usage']['input_tokens'] ?? 0,
            completionTokens: $data['usage']['output_tokens'] ?? 0,
            totalTokens: ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0)
        );

        $stopReason = match ($data['stop_reason'] ?? '') {
            'end_turn' => 'stop',
            'max_tokens' => 'max_tokens',
            'tool_use' => 'tool_calls',
            'stop_sequence' => 'stop',
            default => $data['stop_reason'] ?? '',
        };

        return new LlmResponse(
            content: $content,
            toolCalls: $toolCalls,
            usage: $usage,
            model: $data['model'] ?? '',
            rawResponse: $body,
            stopReason: $stopReason,
        );
    }

    private function getHttpClient(): ClientInterface
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'base_uri' => self::API_BASE_URL,
                'timeout' => self::TIMEOUT,
            ]);
        }

        return $this->httpClient;
    }

    private function getApiKey(): string
    {
        $apiKey = $this->config['api_key'] ?? $_ENV['ANTHROPIC_API_KEY'] ?? null;

        if (empty($apiKey)) {
            throw new LlmConfigurationException(
                'Anthropic API key not provided. Set it in config or ANTHROPIC_API_KEY environment variable.'
            );
        }

        return $apiKey;
    }
}
