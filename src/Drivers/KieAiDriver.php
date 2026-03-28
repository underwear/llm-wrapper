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
use Underwear\LlmWrapper\LlmResponse\ToolCall;
use Underwear\LlmWrapper\LlmResponse\Usage;

class KieAiDriver implements LlmDriverInterface
{
    private const API_BASE_URL = 'https://api.kie.ai';
    private const ENDPOINT_CHAT = '/gpt-5-2/v1/chat/completions';
    private const ENDPOINT_CODEX = '/codex/v1/responses';
    private const DEFAULT_MODEL = 'gpt-5-4';
    private const TIMEOUT = 60.0;

    private const CODEX_MODELS = ['gpt-5-4'];

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
        return 'kie';
    }

    public function sendRequest(ChatBuilder $chatBuilder): LlmResponse
    {
        $model = $this->resolveModel($chatBuilder);

        if ($this->isCodexModel($model)) {
            $payload = $this->buildCodexPayload($chatBuilder, $model);
            $response = $this->makeHttpRequest(self::ENDPOINT_CODEX, $payload);
            return $this->parseCodexResponse($response);
        }

        $payload = $this->buildChatPayload($chatBuilder, $model);
        $response = $this->makeHttpRequest(self::ENDPOINT_CHAT, $payload);
        return $this->parseChatResponse($response);
    }

    private function resolveModel(ChatBuilder $chatBuilder): string
    {
        return $chatBuilder->toArray()['model'] ?? $this->defaultModel ?? self::DEFAULT_MODEL;
    }

    private function isCodexModel(string $model): bool
    {
        return in_array($model, self::CODEX_MODELS, true);
    }

    // -- Chat completions (gpt-5-2) --

    private function buildChatPayload(ChatBuilder $chatBuilder, string $model): array
    {
        $chatArray = $chatBuilder->toArray();

        $payload = [
            'model' => $model,
            'messages' => $chatArray['messages'],
        ];

        if (isset($chatArray['temperature'])) {
            $payload['temperature'] = $chatArray['temperature'];
        }

        if (isset($chatArray['max_tokens'])) {
            $payload['max_tokens'] = $chatArray['max_tokens'];
        }

        if (isset($chatArray['tools']) && !empty($chatArray['tools'])) {
            $payload['tools'] = $this->transformToolsForChat($chatArray['tools']);
        }

        if (isset($chatArray['tool_choice'])) {
            $payload['tool_choice'] = $this->transformToolChoiceForChat($chatArray['tool_choice']);
        }

        if (isset($this->config['reasoning_effort'])) {
            $payload['reasoning_effort'] = $this->config['reasoning_effort'];
        }

        return $payload;
    }

    private function transformToolsForChat(array $tools): array
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

    private function transformToolChoiceForChat(string|array $toolChoice): string|array
    {
        if (is_array($toolChoice) && isset($toolChoice['name'])) {
            return [
                'type' => 'function',
                'function' => ['name' => $toolChoice['name']],
            ];
        }

        return $toolChoice;
    }

    private function parseChatResponse(ResponseInterface $response): LlmResponse
    {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        $this->checkJsonError();
        $this->checkApiError($data);

        if (!isset($data['choices']) || empty($data['choices'])) {
            throw new LlmApiException('No choices returned from Kie.ai API');
        }

        $choice = $data['choices'][0];
        $message = $choice['message'] ?? [];

        $toolCalls = [];
        if (isset($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $toolCall) {
                if (($toolCall['type'] ?? '') === 'function') {
                    $name = $toolCall['function']['name'];
                    $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];
                    $toolCalls[$name] = new ToolCall($name, $arguments);
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

    // -- Codex responses (gpt-5-4) --

    private function buildCodexPayload(ChatBuilder $chatBuilder, string $model): array
    {
        $chatArray = $chatBuilder->toArray();

        $payload = [
            'model' => $model,
            'input' => $chatArray['messages'],
        ];

        if (isset($this->config['reasoning_effort'])) {
            $payload['reasoning'] = ['effort' => $this->config['reasoning_effort']];
        }

        if (isset($chatArray['tools']) && !empty($chatArray['tools'])) {
            $payload['tools'] = $this->transformToolsForCodex($chatArray['tools']);
            $payload['tool_choice'] = 'auto';
        }

        if (isset($chatArray['tool_choice'])) {
            $tc = $chatArray['tool_choice'];
            if (is_array($tc) && isset($tc['name'])) {
                $payload['tool_choice'] = [
                    'type' => 'function',
                    'name' => $tc['name'],
                ];
            } elseif ($tc === 'none') {
                $payload['tool_choice'] = 'none';
            } else {
                $payload['tool_choice'] = 'auto';
            }
        }

        return $payload;
    }

    private function transformToolsForCodex(array $tools): array
    {
        $result = [];

        foreach ($tools as $tool) {
            $result[] = [
                'type' => 'function',
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'parameters' => $tool['parameters'] ?? [
                    'type' => 'object',
                    'properties' => [],
                ],
            ];
        }

        return $result;
    }

    private function parseCodexResponse(ResponseInterface $httpResponse): LlmResponse
    {
        $body = $httpResponse->getBody()->getContents();

        $response = $this->extractCompletedResponse($body);

        $this->checkApiError($response);

        if (!isset($response['output']) || empty($response['output'])) {
            throw new LlmApiException('No output returned from Kie.ai API');
        }

        $content = '';
        $toolCalls = [];

        foreach ($response['output'] as $block) {
            if ($block['type'] === 'message' && isset($block['content'])) {
                foreach ($block['content'] as $contentBlock) {
                    if ($contentBlock['type'] === 'output_text') {
                        $content .= $contentBlock['text'] ?? '';
                    }
                }
            }

            if ($block['type'] === 'function_call') {
                $name = $block['name'] ?? '';
                $arguments = json_decode($block['arguments'] ?? '{}', true) ?: [];
                $toolCalls[$name] = new ToolCall($name, $arguments);
            }
        }

        $usage = $response['usage'] ?? [];

        $stopReason = match ($response['status'] ?? '') {
            'completed' => 'stop',
            default => $response['status'] ?? '',
        };

        return new LlmResponse(
            content: $content,
            toolCalls: $toolCalls,
            usage: new Usage(
                promptTokens: $usage['input_tokens'] ?? 0,
                completionTokens: $usage['output_tokens'] ?? 0,
                totalTokens: $usage['total_tokens'] ?? 0
            ),
            model: $response['model'] ?? '',
            rawResponse: $body,
            stopReason: $stopReason,
        );
    }

    private function extractCompletedResponse(string $sseBody): array
    {
        $lastData = null;

        foreach (explode("\n", $sseBody) as $line) {
            if (str_starts_with($line, 'data: ')) {
                $lastData = substr($line, 6);
            }
        }

        if ($lastData === null) {
            throw new LlmApiException('No SSE data events in Kie.ai response');
        }

        $event = json_decode($lastData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LlmApiException('Invalid JSON in Kie.ai SSE event');
        }

        if (!isset($event['response'])) {
            throw new LlmApiException('No response object in Kie.ai completed event');
        }

        return $event['response'];
    }

    // -- Shared --

    private function makeHttpRequest(string $endpoint, array $payload): ResponseInterface
    {
        $client = $this->getHttpClient();
        $apiKey = $this->getApiKey();

        try {
            return $client->post(self::API_BASE_URL . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => self::TIMEOUT,
            ]);
        } catch (RequestException $e) {
            throw new LlmApiException(
                'Kie.ai API request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    private function checkJsonError(): void
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LlmApiException('Invalid JSON response from Kie.ai API');
        }
    }

    private function checkApiError(array $data): void
    {
        if (isset($data['error'])) {
            throw new LlmApiException(
                'Kie.ai API Error: ' . ($data['error']['message'] ?? 'Unknown error'),
                $data['error']['code'] ?? 0
            );
        }

        if (isset($data['code']) && $data['code'] !== 200) {
            throw new LlmApiException(
                'Kie.ai API Error: ' . ($data['msg'] ?? 'Unknown error'),
                (int) $data['code']
            );
        }
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
                'Kie.ai API key not provided. Set it in config. Get your key at https://kie.ai/api-key'
            );
        }

        return $apiKey;
    }
}
