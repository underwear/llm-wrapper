# LLM Wrapper

A fluent PHP wrapper for LLM APIs with function calling support. Build type-safe, readable interactions with OpenAI and
other LLM providers.

## Installation

```bash
composer require underwear/llm-wrapper
```

## Requirements

- PHP 8.0 or higher

## Quick Start

```php
use Underwear\LlmWrapper\ChatBuilder;
use Underwear\LlmWrapper\FunctionBuilder;
use Underwear\LlmWrapper\FunctionParam;
use Underwear\LlmWrapper\FunctionCallMode;

$payload = ChatBuilder::make()
    ->model('gpt-4')
    ->temperature(0.7)
    ->system('You are a helpful assistant.')
    ->user('What is the weather like?')
    ->toArray();

// Send to your LLM API
// $response = $openai->chat()->create($payload);
```

## Function Calling

Define functions with a clean, type-safe API:

```php
$payload = ChatBuilder::make()
    ->model('gpt-4')
    ->system('You are a product management assistant.')
    ->user('Create a new product.')
    ->function('createProduct', function (FunctionBuilder $fn) {
        $fn->description('Creates a new product in the system')
            ->param(
                FunctionParam::string('name')
                    ->description('Product name')
                    ->required()
            )
            ->param(
                FunctionParam::int('price')
                    ->description('Price in cents')
                    ->min(100)
                    ->max(100000)
                    ->required()
            )
            ->param(
                FunctionParam::string('category')
                    ->description('Product category')
                    ->enum(['books', 'clothing', 'electronics'])
                    ->required()
            );
    })
    ->functionCall(FunctionCallMode::auto())
    ->toArray();
```

## Advanced Examples

### Working with Arrays

```php
// Array of strings
->param(
    FunctionParam::arrayOf(
        FunctionParam::string('tag')->description('Tag')
    )->description('List of tags')
)

// Array of objects
->param(
    FunctionParam::arrayOf(
        FunctionParam::object('variant')->props([
            FunctionParam::string('sku')->description('SKU')->required(),
            FunctionParam::int('stock')->description('Stock quantity')->required(),
        ])
    )->description('Product variants')
)
```

### Nested Objects

```php
->param(
    FunctionParam::object('dimensions')
        ->description('Product dimensions')
        ->props([
            FunctionParam::float('width')->description('Width in cm')->required(),
            FunctionParam::float('height')->description('Height in cm')->required(),
            FunctionParam::float('depth')->description('Depth in cm')->required(),
        ])
)
```

### Complete Example

```php
use Underwear\LlmWrapper\ChatBuilder;
use Underwear\LlmWrapper\FunctionBuilder;
use Underwear\LlmWrapper\FunctionParam;
use Underwear\LlmWrapper\FunctionCallMode;

$payload = ChatBuilder::make()
    ->model('gpt-4')
    ->temperature(0.7)
    ->system('You are a product management assistant.')
    ->user('Create a new laptop product.')
    ->function('createProduct', function (FunctionBuilder $fn) {
        $dimensionsObj = FunctionParam::object('dimensions')
            ->description('Dimensions')
            ->props([
                FunctionParam::float('width')->description('Width in cm')->required(),
                FunctionParam::float('height')->description('Height in cm')->required(),
                FunctionParam::float('depth')->description('Depth in cm')->required(),
            ]);

        $fn->description('Creates a new product in the system')
            ->param(
                FunctionParam::string('name')
                    ->description('Product name')
                    ->required()
            )
            ->param(
                FunctionParam::int('price')
                    ->description('Price in cents')
                    ->min(100)
                    ->max(100000)
                    ->required()
            )
            ->param(
                FunctionParam::string('category')
                    ->description('Product category')
                    ->enum(['books', 'clothing', 'electronics'])
                    ->required()
            )
            ->param(
                FunctionParam::arrayOf(
                    FunctionParam::string('tag')->description('Tag')
                )->description('List of tags')
            )
            ->param(
                FunctionParam::arrayOf(
                    FunctionParam::object('variant')->props([
                        FunctionParam::string('sku')->description('SKU')->required(),
                        FunctionParam::int('stock')->description('Stock quantity')->required(),
                    ])
                )->description('Product variants')
            )
            ->param($dimensionsObj);
    })
    ->functionCall(FunctionCallMode::auto())
    ->toArray();

// The payload is now ready to send to OpenAI
// $response = $openai->chat()->create($payload);
```

## API Reference

### ChatBuilder

| Method                                       | Description                       |
|----------------------------------------------|-----------------------------------|
| `make()`                                     | Create a new ChatBuilder instance |
| `model(string $model)`                       | Set the model (e.g., 'gpt-4')     |
| `temperature(float $temp)`                   | Set temperature (0.0-2.0)         |
| `system(string $content)`                    | Add system message                |
| `user(string $content)`                      | Add user message                  |
| `assistant(string $content)`                 | Add assistant message             |
| `function(string $name, callable $callback)` | Define a function                 |
| `functionCall(string $mode)`                 | Set function call mode            |
| `toArray()`                                  | Build the final payload           |

### FunctionParam

| Method                         | Description              |
|--------------------------------|--------------------------|
| `string(string $name)`         | Create string parameter  |
| `int(string $name)`            | Create integer parameter |
| `float(string $name)`          | Create float parameter   |
| `bool(string $name)`           | Create boolean parameter |
| `object(string $name)`         | Create object parameter  |
| `arrayOf(FunctionParam $type)` | Create array parameter   |
| `description(string $desc)`    | Set description          |
| `required()`                   | Mark as required         |
| `enum(array $values)`          | Set allowed values       |
| `min(int $min)`                | Set minimum value        |
| `max(int $max)`                | Set maximum value        |
| `props(array $properties)`     | Set object properties    |

### FunctionCallMode

| Constant                                   | Description              |
|--------------------------------------------|--------------------------|
| `FunctionCallMode::auto()`                 | Let the model decide     |
| `FunctionCallMode::none()`                 | Disable function calling |
| `FunctionCallMode::specific(string $name)` | Force specific function  |

## Testing

```bash
# Run tests
composer test

# Fix code style
composer cs-fix

# Run static analysis
composer analyze
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- **underwear** - [GitHub](https://github.com/underwear)
