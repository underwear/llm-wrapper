# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

PHP library (`underwear/llm-wrapper`) providing a fluent API for interacting with OpenAI and Anthropic LLMs, with tool calling support. Requires PHP 8.1+.

## Commands

```bash
# Install dependencies (via Docker)
make install

# Run tests (via Docker)
make test

# Run tests directly (requires PHP 8.1+ and vendor installed)
vendor/bin/phpunit tests/

# Code style
composer cs-fix          # or: vendor/bin/php-cs-fixer fix

# Static analysis
composer analyze         # or: vendor/bin/phpstan analyse
```

## Architecture

**Driver pattern** — `LlmDriverInterface` defines the contract. Three implementations (`OpenAiDriver`, `AnthropicDriver`, `KieAiDriver`) handle provider-specific API formatting, HTTP calls, and response parsing. `LlmClient` is the entry point that wraps a driver and delegates via `send()`.

**Builder pattern** — `ChatBuilder` provides a fluent interface for constructing requests (`->system()->user()->assistant()->model()->temperature()->maxTokens()->tool()->send()`). `ToolBuilder` and `FunctionParam` build JSON Schema definitions for tool calling.

**Request flow**: `LlmClient::chat()` creates a `ChatBuilder` → user chains methods → `->send()` calls back to `LlmClient::send()` → driver's `sendRequest()` builds payload, makes HTTP request, parses into `LlmResponse`.

**After-send hooks**: `LlmClient::after(callable)` registers callbacks invoked with `(ChatBuilder, LlmResponse, LlmClient)` after each request.

### Key provider differences

- **OpenAI**: Uses `tools`/`tool_choice` fields with `{type: 'function', function: {...}}` wrapper, Bearer auth, messages include system role directly.
- **Anthropic**: Transforms tools to `{name, description, input_schema}` format, uses `x-api-key` header, extracts system messages to a separate `system` field.
- **Kie.ai**: Two modes — chat (gpt-5-2, OpenAI-compatible) and codex (gpt-5-4, SSE stream responses).

### Stop reason normalization

All drivers normalize stop reasons to a common set: `stop`, `max_tokens`, `tool_calls`, `content_filter`.

## Source Layout

- `src/LlmClient.php` — Factory methods (`::openai()`, `::anthropic()`, `::claude()`, `::kie()`, `::make()`) and hook system
- `src/LlmDriverInterface.php` — Driver contract
- `src/Drivers/` — Provider implementations (`OpenAiDriver`, `AnthropicDriver`, `KieAiDriver`)
- `src/ChatBuilder/` — Request building (`ChatBuilder`, `ToolBuilder`, `ToolChoice`, `FunctionParam`)
- `src/LlmResponse/` — Response objects (`LlmResponse`, `ToolCall`, `Usage`)
- `src/Exceptions/` — `LlmApiException`, `LlmConfigurationException`
- `tests/ChatBuilder/` — Unit tests for builders (no integration/API tests)

## Conventions

- `declare(strict_types=1)` in all source files
- Namespace: `Underwear\LlmWrapper\`
- Uses PHP 8.1+ features: constructor property promotion, named arguments, readonly properties
- All drivers default to 60s timeout and define default models as class constants
