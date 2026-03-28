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

class OpenAiDriver implements LlmDriverInterface
{
    private const API_BASE_URL = 'https://api.openai.com/v1';
    private const DEFAULT_MODEL = 'gpt-4.1';
    private const TIMEOUT = 60.0;

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
        return 'openai';
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

        $payload = [
            'model' => $model,
            'messages' => $this->transformMessages($chatArray['messages']),
        ];

        if (isset($chatArray['temperature'])) {
            $payload['temperature'] = $chatArray['temperature'];
        }

        if (isset($chatArray['max_tokens'])) {
            $payload['max_tokens'] = $chatArray['max_tokens'];
        }

        if (isset($chatArray['tools']) && !empty($chatArray['tools'])) {
            $payload['tools'] = $this->transformTools($chatArray['tools']);
        }

        if (isset($chatArray['tool_choice'])) {
            $payload['tool_choice'] = $this->transformToolChoice($chatArray['tool_choice']);
        }

        return $payload;
    }

    private function transformMessages(array $messages): array
    {
        $result = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'tool_result') {
                $result[] = [
                    'role' => 'tool',
                    'tool_call_id' => $message['tool_call_id'],
                    'content' => $message['content'],
                ];
            } elseif (isset($message['_tool_calls'])) {
                $apiMessage = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?: null,
                    'tool_calls' => [],
                ];
                foreach ($message['_tool_calls'] as $call) {
                    $apiMessage['tool_calls'][] = [
                        'id' => $call['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $call['name'],
                            'arguments' => json_encode($call['arguments']),
                        ],
                    ];
                }
                $result[] = $apiMessage;
            } else {
                $result[] = [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }
        }

        return $result;
    }

    private function transformTools(array $tools): array
    {
        $result = [];

        foreach ($tools as $tool) {
            $result[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ];
        }

        return $result;
    }

    private function transformToolChoice(string|array $toolChoice): string|array
    {
        if (is_array($toolChoice) && isset($toolChoice['name'])) {
            return [
                'type' => 'function',
                'function' => ['name' => $toolChoice['name']],
            ];
        }

        return $toolChoice;
    }

    private function makeHttpRequest(array $payload): ResponseInterface
    {
        $client = $this->getHttpClient();
        $apiKey = $this->getApiKey();

        try {
            return $client->post(self::API_BASE_URL . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => self::TIMEOUT,
            ]);
        } catch (RequestException $e) {
            throw new LlmApiException(
                'OpenAI API request failed: ' . $e->getMessage(),
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
            throw new LlmApiException('Invalid JSON response from OpenAI API');
        }

        if (isset($data['error'])) {
            throw new LlmApiException(
                'OpenAI API Error: ' . ($data['error']['message'] ?? 'Unknown error'),
                $data['error']['code'] ?? 0
            );
        }

        if (!isset($data['choices']) || empty($data['choices'])) {
            throw new LlmApiException('No choices returned from OpenAI API');
        }

        $choice = $data['choices'][0];
        $message = $choice['message'] ?? [];

        $toolCalls = [];
        if (isset($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $toolCall) {
                if ($toolCall['type'] === 'function') {
                    $toolCalls[] = new ToolCall(
                        id: $toolCall['id'],
                        name: $toolCall['function']['name'],
                        arguments: json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [],
                    );
                }
            }
        }

        $usage = new Usage(
            promptTokens: $data['usage']['prompt_tokens'] ?? 0,
            completionTokens: $data['usage']['completion_tokens'] ?? 0,
            totalTokens: $data['usage']['total_tokens'] ?? 0
        );

        $stopReason = match ($choice['finish_reason'] ?? '') {
            'stop' => 'stop',
            'length' => 'max_tokens',
            'tool_calls' => 'tool_calls',
            'content_filter' => 'content_filter',
            default => $choice['finish_reason'] ?? '',
        };

        return new LlmResponse(
            content: $message['content'] ?? '',
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
        $apiKey = $this->config['api_key'] ?? null;

        if (empty($apiKey)) {
            throw new LlmConfigurationException(
                'OpenAI API key not provided. Set it in config or OPENAI_API_KEY environment variable.'
            );
        }

        return $apiKey;
    }
}
