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
use Underwear\LlmWrapper\LlmResponse\FunctionCall;

class AnthropicDriver implements LlmDriverInterface
{
    private const API_BASE_URL = 'https://api.anthropic.com/v1';
    private const DEFAULT_MODEL = 'claude-3-7-sonnet-latest';
    private const API_VERSION = '2023-06-01';
    private const TIMEOUT = 60.0; // Anthropic может быть медленнее
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

        // Use model from ChatBuilder or fall back to default
        $model = $chatArray['model'] ?? $this->defaultModel ?? self::DEFAULT_MODEL;

        // Transform messages for Anthropic format
        $messages = $this->transformMessages($chatArray['messages']);
        $systemMessage = $this->extractSystemMessage($chatArray['messages']);

        $payload = [
            'model' => $model,
            'max_tokens' => $this->config['max_tokens'] ?? self::MAX_TOKENS_DEFAULT,
            'messages' => $messages,
        ];

        // Add system message if present
        if ($systemMessage !== null) {
            $payload['system'] = $systemMessage;
        }

        // Add optional parameters if present
        if (isset($chatArray['temperature'])) {
            $payload['temperature'] = $chatArray['temperature'];
        }

        // Transform functions to tools for Anthropic
        if (isset($chatArray['functions']) && !empty($chatArray['functions'])) {
            $payload['tools'] = $this->transformFunctionsToTools($chatArray['functions']);
        }

        // Handle tool use (Anthropic doesn't have direct function_call equivalent)
        if (isset($chatArray['function_call']) && $chatArray['function_call'] !== 'none') {
            $payload['tool_choice'] = ['type' => 'auto'];
        }

        return $payload;
    }

    private function transformMessages(array $messages): array
    {
        $transformed = [];

        foreach ($messages as $message) {
            // Skip system messages - they're handled separately
            if ($message['role'] === 'system') {
                continue;
            }

            $transformed[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        $transformed[] = [
            'role' => 'user',
            'content' => 'Please do your job without asking additional questions.',
        ];

        return $transformed;
    }

    private function extractSystemMessage(array $messages): ?string
    {
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                return $message['content'];
            }
        }

        return null;
    }

    private function transformFunctionsToTools(array $functions): array
    {
        $tools = [];

        foreach ($functions as $function) {
            $tools[] = [
                'name' => $function['name'],
                'description' => $function['description'] ?? '',
                'input_schema' => $function['parameters'] ?? [
                        'type' => 'object',
                        'properties' => []
                    ],
            ];
        }

        return $tools;
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

        // Parse content and function calls
        $content = '';
        $functionCalls = [];

        foreach ($data['content'] as $contentBlock) {
            if ($contentBlock['type'] === 'text') {
                $content .= $contentBlock['text'];
            } elseif ($contentBlock['type'] === 'tool_use') {
                $name = $contentBlock['name'];
                $arguments = $contentBlock['input'] ?? [];
                $functionCalls[$name] = new FunctionCall($name, $arguments);
            }
        }

        // Parse usage
        $usage = new Usage(
            promptTokens: $data['usage']['input_tokens'] ?? 0,
            completionTokens: $data['usage']['output_tokens'] ?? 0,
            totalTokens: ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0)
        );

        return new LlmResponse(
            content: $content,
            functionCalls: $functionCalls,
            usage: $usage,
            model: $data['model'] ?? '',
            rawResponse: $body
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