<h1 align="center">ProbeLLM</h1>

<p align="center">
  A PHP testing framework for LLM-powered agents.<br>
  Built on top of PHPUnit / Pest.
</p>

<p align="center">
  <a href="#installation">Installation</a> &middot;
  <a href="#quick-start">Quick Start</a> &middot;
  <a href="#features">Features</a> &middot;
  <a href="#cassette-system">Cassettes</a> &middot;
  <a href="#providers">Providers</a> &middot;
  <a href="#license">License</a>
</p>

---

## Why ProbeLLM?

Testing LLM agents is hard. Responses are non-deterministic, API calls are slow and expensive, and tool-calling flows require multi-turn orchestration.

ProbeLLM solves this with:

- **Fluent DSL** for writing multi-turn dialog tests
- **Cassette record/replay** so tests run offline, fast, and deterministic
- **LLM-as-judge** assertions for evaluating response quality with natural language criteria
- **Tool calling** support with auto-resolution of `tool_call_id`
- **PHPUnit attributes** for declarative test configuration

## Installation

```bash
composer require probellm/probellm --dev
```

Requires PHP 8.4+ and ext-curl.

## Quick Start

```php
use ProbeLLM\AgentTestCase;
use ProbeLLM\Attributes\AgentSystem;
use ProbeLLM\Attributes\AgentModel;
use ProbeLLM\Attributes\AgentReplayMode;
use ProbeLLM\DSL\AnswerExpectations;

#[AgentSystem('You are a helpful assistant. Always respond in valid JSON.')]
#[AgentModel('gpt-4o')]
#[AgentReplayMode]
class MyAgentTest extends AgentTestCase
{
    protected function resolveProvider(): LLMProvider
    {
        return OpenAICompatibleProvider::openAI(getenv('OPENAI_API_KEY'));
    }

    public function test_greeting(): void
    {
        $this->dialog()
            ->user('Return JSON with key "greeting" and value "hello".')
            ->answer(function (AnswerExpectations $a) {
                $a->assertJson()
                  ->assertJsonPath('$.greeting', equals: 'hello');
            });
    }
}
```

First run calls the real API and records cassettes automatically. All subsequent runs use cached responses — instant, no API calls:

```bash
./vendor/bin/phpunit
```

## Features

### Multi-turn Dialogs

Chain `.user()` / `.answer()` / `.toolResult()` calls to test full conversation flows:

```php
$this->dialog()
    ->user('Return JSON: {"count": 1}')
    ->answer(function (AnswerExpectations $a) {
        $a->assertJson()
          ->assertJsonPath('$.count', equals: 1);
    })
    ->user('Now increment the count.')
    ->answer(function (AnswerExpectations $a) {
        $a->assertJsonPath('$.count', equals: 2);
    });
```

### JSON Assertions

```php
->answer(function (AnswerExpectations $a) {
    $a->assertJson()                                          // valid JSON
      ->assertJsonPath('$.name', equals: 'Alice')             // exact match
      ->assertJsonPath('$.bio', contains: 'engineer')         // substring
      ->assertJsonPath('$.bio', notContains: 'manager')       // negative substring
      ->assertJsonPath('$.items[0].id', notEmpty: true);      // nested array access
})
```

### Tool Calling

Define tools via `ToolContract`, assert on calls and arguments:

```php
#[AgentTools(SearchTool::class)]
public function test_agent_searches(): void
{
    $this->dialog()
        ->user('Search for "PHP 8.4 features".')
        ->answer(function (AnswerExpectations $a) {
            $a->assertToolCalled('search')
              ->assertToolArgs('search', function (array $args) {
                  self::assertStringContainsString('PHP', $args['query']);
              });
        })
        ->toolResult('search', [
            'results' => [['title' => 'PHP 8.4 Released', 'url' => 'https://php.net']],
        ])
        ->answer(function (AnswerExpectations $a) {
            self::assertNotEmpty($a->lastMessage());
        });
}
```

### LLM-as-Judge

Use natural language criteria to evaluate responses:

```php
->answer(function (AnswerExpectations $a) {
    $a->assertJson()
      ->assertByPrompt('The response contains a healthy breakfast suggestion')
      ->assertByPrompt('No excessive sugar is recommended');
})
```

Judge model and temperature can be configured per-call, per-method, or per-class:

```php
// Per-call override
$a->assertByPrompt('Criteria here', model: 'gpt-4o', temperature: 0.1);

// Via attributes
#[JudgeModel('gpt-4o-mini')]
#[JudgeTemperature(0.0)]
```

### PHPUnit Attributes

Declarative configuration at class or method level:

| Attribute | Scope | Description |
|-----------|-------|-------------|
| `#[AgentSystem('...')]` | Class / Method | System prompt |
| `#[AgentSystemFile('path')]` | Class / Method | System prompt from file |
| `#[AgentModel('gpt-4o')]` | Class / Method | Model name |
| `#[AgentTemperature(0.7)]` | Class / Method | Sampling temperature |
| `#[AgentTools(SearchTool::class)]` | Class / Method | Enable tool calling |
| `#[AgentReplayMode]` | Class / Method | Enforce cassette-only mode |
| `#[JudgeModel('gpt-4o-mini')]` | Class / Method | Judge model |
| `#[JudgeTemperature(0.0)]` | Class / Method | Judge temperature |

Method-level attributes override class-level. Multiple `#[AgentSystem]` and `#[AgentSystemFile]` are concatenated.

## Cassette System

Cassettes record LLM responses to JSON files in `tests/cassettes/`. Each cassette is keyed by a SHA256 hash of all inputs (system prompt, messages, model, temperature, tools, test name, turn index) — any change produces a new key.

**Decision logic per turn:**

| Cassette exists? | Replay mode? | Result |
|:---:|:---:|---|
| Yes | - | Load from cassette |
| No | Yes | Call API, save cassette |
| No | No | Call API (no caching) |

## Providers

### OpenAI-compatible (OpenAI, OpenRouter, Groq, Together, Ollama, etc.)

```php
protected function resolveProvider(): LLMProvider
{
    return new OpenAICompatibleProvider(
        apiKey: getenv('LLM_API_KEY'),
        baseUrl: 'https://api.openai.com/v1',
    );

    // Or use factory methods:
    // return OpenAICompatibleProvider::openAI(getenv('OPENAI_API_KEY'));
    // return OpenAICompatibleProvider::openRouter(getenv('OPENROUTER_API_KEY'));
}
```

### Anthropic (Claude)

```php
protected function resolveProvider(): LLMProvider
{
    return new AnthropicProvider(apiKey: getenv('ANTHROPIC_API_KEY'));
}
```

### Separate judge provider

```php
protected function resolveJudgeProvider(): ?LLMProvider
{
    return new AnthropicProvider(apiKey: getenv('ANTHROPIC_API_KEY'));
}
```

## Exception Hierarchy

All exceptions extend `ProbeLLMException` (which extends `RuntimeException`), so you can catch them granularly or broadly:

| Exception | When |
|-----------|------|
| `CassetteMissingException` | Replay mode, cassette not found |
| `ProviderException` | HTTP/curl errors from LLM API |
| `InvalidResponseException` | Invalid JSON from provider or judge |
| `ToolResolutionException` | Tool class issues, missing tool_call_id |
| `ConfigurationException` | Missing ext-curl, file not found, no provider configured |

## Environment Variables

| Variable | Description |
|----------|-------------|
| `LLM_API_KEY` | API key for your LLM provider |
| `LLM_BASE_URL` | Provider endpoint (default: `https://api.openai.com/v1`) |

## License

MIT
