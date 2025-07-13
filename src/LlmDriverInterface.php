<?php

namespace Underwear\LlmWrapper;

use GuzzleHttp\ClientInterface;
use Underwear\LlmWrapper\ChatBuilder\ChatBuilder;
use Underwear\LlmWrapper\LlmResponse\LlmResponse;

interface LlmDriverInterface
{
    public function setDefaultModel(string $model): void;

    public function setConfig(array $config): void;

    public function setHttpClient(ClientInterface $client): void;

    public function getName(): string;

    public function sendRequest(ChatBuilder $chatBuilder): LlmResponse;
}