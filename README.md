# LLM Wrapper

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Fluent PHP wrapper for LLM APIs. One interface for OpenAI, Anthropic Claude, and Kie.ai — with tool calling, structured parameters, and response normalization.

```php
$response = LlmClient::openai(['api_key' => 'sk-...'])
    ->chat()
    ->system('You are a helpful assistant.')
    ->user('What is the capital of France?')
    ->send();

echo $response->text(); // Paris
```

## Why?

- **One API for all providers** — switch between OpenAI, Claude, and Kie.ai by changing one line
- **Fluent builder** — readable, chainable, IDE-friendly
- **Tool calling** — type-safe parameter definitions with JSON Schema generation
- **Reusable tools** — define once, use across multiple chats
- **Normalized responses** — consistent `stopReason`, `usage`, and `toolCalls` across providers

## Installation

```bash
composer require underwear/llm-wrapper
```

Requires PHP 8.1+.

## Quick Start

```php
use Underwear\LlmWrapper\LlmClient;

// OpenAI
$client = LlmClient::openai(['api_key' => 'sk-...']);

// Anthropic Claude
$client = LlmClient::claude(['api_key' => 'sk-ant-...']);

// Kie.ai
$client = LlmClient::kie(['api_key' => '...']);

// Or use the generic factory
$client = LlmClient::make('openai', ['api_key' => 'sk-...'], model: 'gpt-4.1-mini');
```

## Chat

### Simple Message

```php
$response = $client->chat()
    ->user('Who wrote "1984"?')
    ->send();

echo $response->text(); // George Orwell
```

### Multi-turn Conversation

```php
$response = $client->chat()
    ->system('You are a helpful historian.')
    ->user('Who discovered penicillin?')
    ->assistant('Alexander Fleming discovered penicillin in 1928.')
    ->user('Tell me more about his discovery.')
    ->send();
```

### Model, Temperature, Max Tokens

```php
$response = $client->chat()
    ->model('gpt-4.1-mini')
    ->temperature(0.9)
    ->maxTokens(1000)
    ->user('Write a short poem about the sea.')
    ->send();
```

## Tool Calling

### Inline Definition

```php
use Underwear\LlmWrapper\ChatBuilder\ToolChoice;

$response = $client->chat()
    ->tool('createProfile', function ($t) {
        $t->stringParam('firstName')->required();
        $t->stringParam('lastName')->required();
        $t->intParam('age')->min(18)->max(60)->required();
    })
    ->toolChoice(ToolChoice::specific('createProfile'))
    ->send();

$profile = $response->tool('createProfile');
echo $profile->get('firstName'); // Emma
echo $profile->lastName;         // magic property access works too
```

### Reusable Tools

Define a tool once, use it in multiple chats:

```php
use Underwear\LlmWrapper\ChatBuilder\ToolBuilder;

$weatherTool = ToolBuilder::create('get_weather')
    ->description('Get current weather for a city');
$weatherTool->stringParam('city')->required();
$weatherTool->stringParam('units')->enum(['celsius', 'fahrenheit']);

$london  = $client->chat()->tool($weatherTool)->user('Weather in London?')->send();
$tokyo   = $client->chat()->tool($weatherTool)->user('Weather in Tokyo?')->send();
```

### Multiple Tools

```php
use Underwear\LlmWrapper\ChatBuilder\ToolBuilder;
use Underwear\LlmWrapper\ChatBuilder\ToolChoice;
use Underwear\LlmWrapper\ChatBuilder\FunctionParam;

$response = $client->chat()
    ->system('You are an assistant that can create users and send notifications.')
    ->user('Create a new user John')
    ->tool('create_user', function (ToolBuilder $t) {
        $t->description('Creates a new user account');
        $t->param(FunctionParam::string('username')->required());
        $t->param(FunctionParam::string('email')->required());
        $t->param(FunctionParam::int('age')->min(18));
    })
    ->tool('send_notification', function (ToolBuilder $t) {
        $t->description('Send a notification');
        $t->param(FunctionParam::string('message')->required());
        $t->param(FunctionParam::string('priority')->enum(['low', 'medium', 'high']));
    })
    ->toolChoice(ToolChoice::auto())
    ->send();

if ($response->called('create_user')) {
    $args = $response->tool('create_user')->getArguments();
    // ['username' => 'john', 'email' => 'john@...', 'age' => 25]
}
```

### Tool Choice Modes

```php
use Underwear\LlmWrapper\ChatBuilder\ToolChoice;

->toolChoice(ToolChoice::auto())                  // model decides
->toolChoice(ToolChoice::required())              // must call a tool
->toolChoice(ToolChoice::specific('tool_name'))   // must call this specific tool
->toolChoice(ToolChoice::none())                  // no tool calls
```

## Parameter Types

Build complex JSON Schema parameters with a fluent API:

```php
use Underwear\LlmWrapper\ChatBuilder\FunctionParam;

// Primitives
FunctionParam::string('name')->required()->description('User name')
FunctionParam::int('age')->min(0)->max(120)
FunctionParam::float('score')->min(0.0)->max(10.0)
FunctionParam::bool('active')

// Enums
FunctionParam::string('status')->enum(['active', 'inactive', 'pending'])

// Nullable
FunctionParam::string('nickname')->nullable()

// Arrays
FunctionParam::stringArray('tags')                    // shortcut for array of strings
FunctionParam::intArray('scores')                     // shortcut for array of integers
FunctionParam::array('items')->arrayOf(               // custom item type
    FunctionParam::float('price')
)->minItems(1)->maxItems(100)

// Objects
FunctionParam::object('address')->props([
    FunctionParam::string('street')->required(),
    FunctionParam::string('city')->required(),
    FunctionParam::string('zip'),
])

// Nested structures
FunctionParam::objectArray('users', [                 // array of objects
    FunctionParam::string('name')->required(),
    FunctionParam::int('age'),
])
```

## Response

```php
$response = $client->chat()->user('Hello')->send();

// Content
$response->text();           // "Hello! How can I help you?"
$response->hasText();        // true

// Tool calls
$response->hasToolCalls();   // true/false
$response->called('name');   // was this tool called?
$response->tool('name');     // ToolCall object or null
$response->tools();          // all ToolCall objects

// ToolCall
$call = $response->tool('get_weather');
$call->get('city');          // "Paris"
$call->get('units', 'c');   // with default value
$call->city;                 // magic property
$call->getArguments();       // ['city' => 'Paris', ...]

// Metadata
$response->stopReason();     // 'stop' | 'max_tokens' | 'tool_calls' | 'content_filter'
$response->model();          // 'gpt-4.1-2025-04-14'
$response->raw();            // raw JSON response body

// Token usage
$response->usage()->promptTokens();      // 14
$response->usage()->completionTokens();  // 28
$response->usage()->totalTokens();       // 42
echo $response->usage();                 // "Usage: 42 tokens (prompt: 14, completion: 28)"
```

## Hooks

Register after-send callbacks for logging, metrics, or debugging:

```php
$client->after(function ($chat, $response, $client) {
    logger()->info('LLM request', [
        'model'       => $response->model(),
        'tokens'      => $response->usage()->totalTokens(),
        'stop_reason' => $response->stopReason(),
    ]);
});

// Register multiple at once
$client->afterMany($loggerHook, $metricsHook, $costTrackerHook);
```

## Supported Providers

| Provider | Factory | Default Model | Auth |
|----------|---------|---------------|------|
| OpenAI | `LlmClient::openai()` | `gpt-4.1` | Bearer token |
| Anthropic | `LlmClient::claude()` | `claude-sonnet-4-20250514` | `x-api-key` header |
| Kie.ai | `LlmClient::kie()` | `gpt-5-4` | Bearer token |

All providers use the same fluent API. Tool calls, stop reasons, and usage stats are normalized across providers.

## Testing

```bash
composer test
```

## License

MIT
