<h1 align="center">ProbeLLM</h1>

<p align="center">
  A PHP testing framework for LLM-powered agents.<br>
  Built on top of PHPUnit / Pest.
</p>

<p align="center">
  <a href="#installation">Installation</a> &middot;
  <a href="#quick-start">Quick Start</a> &middot;
  <a href="#features">Features</a> &middot;
  <a href="#elevenlabs-convai">ElevenLabs</a> &middot;
  <a href="#cassette-system">Cassettes</a> &middot;
  <a href="#providers">Providers</a> &middot;
  <a href="#license">License</a>
</p>

---

## Why ProbeLLM?

Testing LLM agents is hard. Responses are non-deterministic, API calls are slow and expensive, and tool-calling flows require multi-turn orchestration.

ProbeLLM solves this with:

- **Fluent DSL** for writing multi-turn dialog tests
- **ElevenLabs ConvAI** simulation testing with evaluation criteria, tool mocks, and dynamic variables
- **Cassette record/replay** so tests run offline, fast, and deterministic
- **LLM-as-judge** assertions for evaluating response quality with natural language criteria
- **Tool calling** support with auto-resolution of `tool_call_id`
- **Multimodal attachments** (images, PDFs, audio) via local files or URLs
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

### Multimodal Attachments

Send images, PDFs, and audio files alongside user messages:

```php
use ProbeLLM\DTO\Attachment;

$this->dialog()
    ->userWithAttachments('What is in this image?', [
        '/path/to/photo.png',                                    // local file
        Attachment::fromUrl('https://example.com/img.jpg'),      // URL
        Attachment::fromBase64($data, 'image/jpeg'),             // base64
    ])
    ->answer(function (AnswerExpectations $a) {
        $a->assertByPrompt('The response describes the contents of the image');
    });
```

Supported types: `image/*`, `application/pdf`, `audio/*`.

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
| `#[ElevenLabsAgentId('agent_...')]` | Class / Method | ElevenLabs agent ID |
| `#[ElevenLabsAgentId(env: 'VAR')]` | Class / Method | Agent ID from env variable |
| `#[ElevenLabsTurnsLimit(20)]` | Class / Method | Max simulation turns |

Method-level attributes override class-level. Multiple `#[AgentSystem]` and `#[AgentSystemFile]` are concatenated.

## ElevenLabs ConvAI

Test ElevenLabs conversational AI agents using the [simulate-conversation API](https://elevenlabs.io/docs/conversational-ai/customization/personality/simulated-conversations). ProbeLLM sends a simulated user against your agent and lets you assert on the resulting transcript, tool calls, evaluations, and workflow transfers.

### Setup

```php
use ProbeLLM\ElevenLabsTestCase;
use ProbeLLM\Attributes\ElevenLabsAgentId;
use ProbeLLM\Attributes\ElevenLabsTurnsLimit;
use ProbeLLM\DSL\ElevenLabsExpectations;

#[ElevenLabsAgentId(env: 'ELEVENLABS_AGENT_ID')]
#[ElevenLabsTurnsLimit(20)]
class MyVoiceAgentTest extends ElevenLabsTestCase
{
    // Uses ELEVENLABS_API_KEY env var automatically.
    // Override resolveElevenLabsProvider() for custom setup.
}
```

### Simulation Scenario

```php
public function test_greeting(): void
{
    $this->elevenLabs()
        ->withDynamicVariable('companyName', 'Acme Corp')
        ->withUserPrompt('You just called the company, wait for the greeting')
        ->withTurnsLimit(4)
        ->withEvaluation('greeting', 'Agent greeted the user and mentioned the company name')
        ->run(function (ElevenLabsExpectations $e) {
            $e->assertMinTurns(2)
                ->assertAllEvaluationsPassed()
                ->assertByPrompt('The agent greeted the user politely');
        });
}
```

### Dynamic Variables

Pass `{{placeholder}}` values that your agent's prompt references:

```php
$this->elevenLabs()
    ->withDynamicVariable('companyName', 'Acme Corp')
    ->withDynamicVariable('agentName', 'Sarah')
    ->withDynamicVariables([
        'businessHours' => '9am-5pm',
        'maxDiscount' => 15,
    ])
```

### Tool Mocks

Mock tool responses so the agent's tools return predetermined data during simulation:

```php
$this->elevenLabs()
    ->withToolMock('Create_order', ['status' => 'success', 'request_id' => 'REQ-001'])
    ->withToolMock('Transfer-to-number', ['status' => 'transferred'])
```

### Evaluation Criteria

Define criteria that ElevenLabs evaluates against the conversation:

```php
$this->elevenLabs()
    ->withEvaluation('data_collected', 'Agent collected name, phone, and address')
    ->withEvaluation('lead_created', 'Agent used the Create_order tool')
    ->run(function (ElevenLabsExpectations $e) {
        $e->assertAllEvaluationsPassed();      // all criteria passed
        $e->assertEvaluation('data_collected'); // specific criterion passed
        $e->assertEvaluationFailed('some_id');  // specific criterion failed
        $e->assertEvaluationCount(2);           // expected number of results
    });
```

### ElevenLabs Assertions

#### Tool assertions

```php
$e->assertToolCalled('Create_order')           // tool was called at least once
  ->assertToolNotCalled('Dangerous_tool')        // tool was NOT called
  ->assertToolCalledTimes('Create_order', 1)    // exact call count
  ->assertToolExecuted('Create_order')          // called AND executed
  ->assertToolCallCount(2)                       // total tool calls
  ->assertNoToolsCalled()                        // no tools called at all
  ->assertToolCallParam('Create_order', 'name', 'John')  // param value
  ->assertToolCallParamContains('Create_order', 'address', 'Maple')
  ->assertToolCallHasParam('Create_order', 'phone')
  ->assertToolArgs('Create_order', function (array $args) {
      self::assertArrayHasKey('name', $args);
  });
```

#### Transcript assertions

```php
$e->assertTranscriptContains('hello')            // full transcript contains string
  ->assertTranscriptNotContains('error')          // full transcript does NOT contain
  ->assertTranscriptMatchesRegex('/\d{3}-\d{4}/')
  ->assertAgentSaid('How can I help')             // only agent messages
  ->assertAgentNeverSaid('I am an AI')
  ->assertFirstAgentMessage('Welcome')
  ->assertLastAgentMessage('Goodbye')
  ->assertTranscriptRole(0, 'agent')              // role at index
  ->assertTranscriptContent(0, 'exact text')      // content at index
  ->assertMinTurns(4)                             // at least N entries
  ->assertMaxTurns(20);                           // at most N entries
```

#### Workflow / transfer assertions

```php
$e->assertAgentHandled('agent_abc123')            // agent appeared in transcript
  ->assertTransferredToAgent('agent_xyz789')       // conversation transferred
  ->assertWorkflowNodeReached('node_qualifier')
  ->assertAgentCount(2);                           // number of unique agents
```

#### Analysis assertions

```php
$e->assertCallSuccessful()                        // analysis.call_successful = "success"
  ->assertTranscriptSummaryContains('booked');     // analysis.transcript_summary contains
```

#### LLM Judge

```php
$e->assertByPrompt('The agent collected all required information before creating the request');
```

Requires a judge provider. Override `resolveJudgeProvider()` in your test case or set `LLM_API_KEY` / `LLM_BASE_URL` env vars (auto-configured in `ElevenLabsTestCase`).

## Cassette System

Cassettes record LLM responses to JSON files in `tests/cassettes/`. Each cassette is keyed by a SHA256 hash of all inputs (system prompt, messages, model, temperature, tools, test name, turn index) — any change produces a new key.

**Decision logic per turn:**

| Cassette exists? | Replay mode? | Result |
|:---:|:---:|---|
| Yes | - | Load from cassette |
| No | Yes | Call API, save cassette |
| No | No | Call API (no caching) |

ElevenLabs simulations use the same cassette system. The hash is computed from agent ID, user prompt, first message, tool mocks, evaluation criteria, turns limit, dynamic variables, and test name.

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

### ElevenLabs ConvAI

```php
protected function resolveElevenLabsProvider(): ElevenLabsConvaiProvider
{
    return new ElevenLabsProvider(apiKey: getenv('ELEVENLABS_API_KEY'));
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
| `ELEVENLABS_API_KEY` | API key for ElevenLabs ConvAI |
| `ELEVENLABS_AGENT_ID` | Default agent ID for ElevenLabs tests |

## License

MIT
