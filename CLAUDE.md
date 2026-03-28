# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

PHP library (`underwear/llm-wrapper`) providing a fluent API for interacting with OpenAI and Anthropic LLMs, with function calling support. Requires PHP 8.1+.

## Commands

```bash
# Install dependencies (via Docker)
make install

# Run tests (via Docker)
make test

# Run tests directly (requires PHP 8.1+ and vendor installed)
vendor/bin/phpunit

# Code style
composer cs-fix          # or: vendor/bin/php-cs-fixer fix

# Static analysis
composer analyze         # or: vendor/bin/phpstan analyse
```

Note: There is no `phpunit.xml` file yet — the Makefile passes `--configuration phpunit.xml` but it doesn't exist. Tests run via `vendor/bin/phpunit` with default discovery.

## Architecture

**Driver pattern** — `LlmDriverInterface` defines the contract. Two implementations (`OpenAiDriver`, `AnthropicDriver`) handle provider-specific API formatting, HTTP calls, and response parsing. `LlmClient` is the entry point that wraps a driver and delegates via `send()`.

**Builder pattern** — `ChatBuilder` provides a fluent interface for constructing requests (`->system()->user()->assistant()->model()->temperature()->function()->send()`). `FunctionBuilder` and `FunctionParam`/`ObjectProp` build JSON Schema definitions for function calling.

**Request flow**: `LlmClient::chat()` creates a `ChatBuilder` → user chains methods → `->send()` calls back to `LlmClient::send()` → driver's `sendRequest()` builds payload, makes HTTP request, parses into `LlmResponse`.

**After-send hooks**: `LlmClient::after(callable)` registers callbacks invoked with `(ChatBuilder, LlmResponse, LlmClient)` after each request.

### Key provider differences

- **OpenAI**: Uses `functions`/`function_call` fields, Bearer auth, messages include system role directly.
- **Anthropic**: Transforms functions to `tools` format, uses `x-api-key` header, extracts system messages to a separate `system` field, appends a final user message ("Please do your job without asking additional questions.").

## Source Layout

- `src/LlmClient.php` — Factory methods (`::openai()`, `::anthropic()`, `::claude()`, `::make()`) and hook system
- `src/LlmDriverInterface.php` — Driver contract
- `src/Drivers/` — Provider implementations
- `src/ChatBuilder/` — Request building (`ChatBuilder`, `FunctionBuilder`, `FunctionParam`, `FunctionCallMode`, `ObjectProp`)
- `src/LlmResponse/` — Response objects (`LlmResponse`, `FunctionCall`, `Usage`)
- `src/Exceptions/` — `LlmApiException`, `LlmConfigurationException`
- `tests/ChatBuilder/` — Unit tests for builders (no integration/API tests)

## Conventions

- `declare(strict_types=1)` in all source files
- Namespace: `Underwear\LlmWrapper\`
- Uses PHP 8.1+ features: constructor property promotion, named arguments, readonly properties
- Both drivers default to 60s timeout and define default models as class constants
