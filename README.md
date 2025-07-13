# 🤖 LLM Wrapper

A clean and flexible PHP wrapper designed to simplify interactions with LLM like OpenAI GPT and Anthropic Claude.

## 📦 Installation

Install via Composer:

```bash
composer require underwear/llm-wrapper
```

## 🚀 Quick Start

### Using OpenAI GPT

Initialize the client and send your first request:

```php
use Underwear\LlmWrapper\LlmClient;

$client = LlmClient::openai(['api_key' => 'your-key']);

$response = $client->chat()
    ->model('gpt-4')
    ->user('What is the capital of Germany?')
    ->send();

echo $response->text(); // Berlin
```

### Using Anthropic Claude

Quick setup for Anthropic Claude:

```php
use Underwear\LlmWrapper\LlmClient;

$client = LlmClient::claude(['api_key' => 'your-key']);

$response = $client->chat()
    ->user('Explain photosynthesis in simple terms.')
    ->send();

echo $response->text();
```

## 📚Chat Examples (from Basic to Advanced)

### 1. Simple Chat Message

```php
$response = $client->chat()
    ->user('Who wrote "1984"?')
    ->send();

echo $response->text(); // George Orwell

```

### 2. Contextual Conversations

Chain messages for contextual responses:

```php
$chat = $client->chat()
    ->system('You are a helpful historian.')
    ->user('Who discovered penicillin?')
    ->assistant('Alexander Fleming discovered penicillin in 1928.')
    ->user('Tell me more about his discovery.');

$response = $chat->send();

echo $response->text();
```

### 3. Customizing Model and Temperature

Adjust parameters for creative or precise responses:

```php
$response = $client->chat()
    ->model('gpt-4.1-mini')
    ->temperature(0.9)
    ->user('Write a short poem about the sea.')
    ->send();

echo $response->text();
```

## 🛠️ Advanced Function Calling

Define and invoke custom functions the LLM can call:

### 1. Simple Function Definition

```php
use Underwear\LlmWrapper\ChatBuilder\FunctionCallMode;

$response = $client->chat()
    ->system('You are generator of random profiles')
    ->function('createProfile', function ($f) {
        $f->stringParam('firstName')->required();
        $f->stringParam('lastName')->required();
        $f->intParam('age')->min(18)->max(30)->required();
    })
    ->functionCall(FunctionCallMode::specific('createProfile'))
    ->send();

$firstName = $response->function('createProfile')->get('firstName');
echo "Generated name: {$firstName}";
```

### 2. Multi-functions

```php
use Underwear\LlmWrapper\ChatBuilder\FunctionBuilder;
use Underwear\LlmWrapper\ChatBuilder\FunctionCallMode;
use Underwear\LlmWrapper\ChatBuilder\FunctionParam;
use Underwear\LlmWrapper\ChatBuilder\ObjectProp;

$response = $client->chat()
    ->system('You are an assistant capable of creating users and sending notifications.')
    ->user('Now we need to create a user')
    ->function('create_user', function (FunctionBuilder $f) {
        $f->description('Creates a new user account.');
        $f->param(FunctionParam::string('username')->required());
        $f->param(FunctionParam::object('profile')->props([
            ObjectProp::string('first_name')->required(),
            ObjectProp::string('last_name')->required(),
            ObjectProp::int('age')->min(18),
        ]));
    })
    ->function('send_notification', function (FunctionBuilder $f) {
        $f->description('Send a notification message to the user.');
        $f->param(FunctionParam::string('message')->required());
        $f->param(FunctionParam::string('priority')->enum(['low', 'medium', 'high']));
    })
    ->functionCall(FunctionCallMode::auto())
    ->send();

if ($response->hasFunctionCalls()) {
    if ($response->called('create_user')) {
        $args = $response->function('create_user')->getArguments();
        // do something here...
    }
    
    if ($response->called('send_notification')) {
        $args = $response->function('send_notification')->getArguments();
        // do something here...
    }
}
```

### 3. Working with Arrays in Function Parameters

The wrapper provides powerful array handling capabilities with `arrayOf()` method and convenient shortcuts:

```php
use Underwear\LlmWrapper\ChatBuilder\FunctionBuilder;
use Underwear\LlmWrapper\ChatBuilder\FunctionParam;

$response = $client->chat()
    ->system('You are a shopping assistant that helps organize grocery lists.')
    ->user('Create a grocery list with 5 fruits and their estimated prices')
    ->function('create_grocery_list', function (FunctionBuilder $f) {
        $f->description('Creates a structured grocery list');
        
        // Array of strings - using convenience method
        $f->param(FunctionParam::stringArray('fruits')->minItems(3)->maxItems(10));
        
        // Array of numbers - using arrayOf method
        $f->param(FunctionParam::array('prices')
            ->arrayOf(FunctionParam::float('price'))
            ->minItems(3)
            ->maxItems(10)
        );
        
        // Array of integers
        $f->param(FunctionParam::intArray('quantities')->required());
    })
    ->functionCall(FunctionCallMode::auto())
    ->send();

if ($response->called('create_grocery_list')) {
    $args = $response->function('create_grocery_list')->getArguments();
    $fruits = $args['fruits']; // ['apple', 'banana', 'orange', ...]
    $prices = $args['prices']; // [1.99, 0.89, 2.50, ...]
    $quantities = $args['quantities']; // [5, 10, 3, ...]
}
```
