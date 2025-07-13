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

class OpenAiDriver implements LlmDriverInterface
{
    private const API_BASE_URL = 'https://api.openai.com/v1';
    private const DEFAULT_MODEL = 'gpt-4.1';
    private const TIMEOUT = 30.0;

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

        // Use model from ChatBuilder or fall back to default
        $model = $chatArray['model'] ?? $this->defaultModel ?? self::DEFAULT_MODEL;

        $payload = [
            'model' => $model,
            'messages' => $chatArray['messages'],
        ];

        // Add optional parameters if present
        if (isset($chatArray['temperature'])) {
            $payload['temperature'] = $chatArray['temperature'];
        }

        if (isset($chatArray['functions']) && !empty($chatArray['functions'])) {
            $payload['functions'] = $chatArray['functions'];
        }

        if (isset($chatArray['function_call'])) {
            $payload['function_call'] = $chatArray['function_call'];
        }

        return $payload;
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

        // Parse function calls
        $functionCalls = [];
        if (isset($message['function_call'])) {
            $functionCall = $message['function_call'];
            $name = $functionCall['name'];
            $arguments = json_decode($functionCall['arguments'] ?? '{}', true) ?: [];

            $functionCalls[$name] = new FunctionCall($name, $arguments);
        }

        // Parse usage
        $usage = new Usage(
            promptTokens: $data['usage']['prompt_tokens'] ?? 0,
            completionTokens: $data['usage']['completion_tokens'] ?? 0,
            totalTokens: $data['usage']['total_tokens'] ?? 0
        );

        return new LlmResponse(
            content: $message['content'] ?? '',
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
        $apiKey = $this->config['api_key'] ?? null;

        if (empty($apiKey)) {
            throw new LlmConfigurationException(
                'OpenAI API key not provided. Set it in config or OPENAI_API_KEY environment variable.'
            );
        }

        return $apiKey;
    }
}