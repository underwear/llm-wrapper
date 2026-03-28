<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\Tests\ChatBuilder;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Underwear\LlmWrapper\ChatBuilder\ChatBuilder;
use Underwear\LlmWrapper\ChatBuilder\ToolBuilder;
use Underwear\LlmWrapper\LlmClient;
use Underwear\LlmWrapper\LlmResponse\LlmResponse;

class ChatBuilderTest extends TestCase
{
    private MockObject|LlmClient $mockLlmClient;
    private ChatBuilder $chatBuilder;

    protected function setUp(): void
    {
        $this->mockLlmClient = $this->createMock(LlmClient::class);
        $this->chatBuilder = new ChatBuilder($this->mockLlmClient);
    }

    public function testConstructor(): void
    {
        $chatBuilder = new ChatBuilder($this->mockLlmClient);

        $this->assertInstanceOf(ChatBuilder::class, $chatBuilder);
        $this->assertEmpty($chatBuilder->getMessages());
        $this->assertNull($chatBuilder->getModel());
        $this->assertNull($chatBuilder->getTemperature());
        $this->assertNull($chatBuilder->getMaxTokens());
        $this->assertEmpty($chatBuilder->getTools());
        $this->assertNull($chatBuilder->getToolChoice());
    }

    public function testModelMethod(): void
    {
        $result = $this->chatBuilder->model('gpt-4');

        $this->assertSame($this->chatBuilder, $result);
        $this->assertEquals('gpt-4', $this->chatBuilder->getModel());
    }

    public function testTemperatureMethod(): void
    {
        $result = $this->chatBuilder->temperature(0.7);

        $this->assertSame($this->chatBuilder, $result);
        $this->assertEquals(0.7, $this->chatBuilder->getTemperature());
    }

    public function testMaxTokensMethod(): void
    {
        $result = $this->chatBuilder->maxTokens(1000);

        $this->assertSame($this->chatBuilder, $result);
        $this->assertEquals(1000, $this->chatBuilder->getMaxTokens());
    }

    public function testSystemMessage(): void
    {
        $content = 'You are a helpful assistant.';
        $result = $this->chatBuilder->system($content);

        $this->assertSame($this->chatBuilder, $result);

        $messages = $this->chatBuilder->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals([
            'role' => 'system',
            'content' => $content
        ], $messages[0]);
    }

    public function testUserMessage(): void
    {
        $content = 'Hello, how are you?';
        $result = $this->chatBuilder->user($content);

        $this->assertSame($this->chatBuilder, $result);

        $messages = $this->chatBuilder->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals([
            'role' => 'user',
            'content' => $content
        ], $messages[0]);
    }

    public function testAssistantMessage(): void
    {
        $content = 'I am doing well, thank you!';
        $result = $this->chatBuilder->assistant($content);

        $this->assertSame($this->chatBuilder, $result);

        $messages = $this->chatBuilder->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals([
            'role' => 'assistant',
            'content' => $content
        ], $messages[0]);
    }

    public function testMultipleMessages(): void
    {
        $this->chatBuilder
            ->system('You are a helpful assistant.')
            ->user('What is the weather like?')
            ->assistant('I don\'t have access to current weather data.');

        $messages = $this->chatBuilder->getMessages();
        $this->assertCount(3, $messages);

        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('user', $messages[1]['role']);
        $this->assertEquals('assistant', $messages[2]['role']);
    }

    public function testToolWithCallback(): void
    {
        $result = $this->chatBuilder->tool('get_weather', function (ToolBuilder $builder) {
            $builder->description('Get current weather for a location');
            $builder->stringParam('location')->required()->description('The city name');
            $builder->stringParam('units')->enum(['celsius', 'fahrenheit']);
        });

        $this->assertSame($this->chatBuilder, $result);

        $tools = $this->chatBuilder->getTools();
        $this->assertCount(1, $tools);

        $tool = $tools[0];
        $this->assertEquals('get_weather', $tool['name']);
        $this->assertEquals('Get current weather for a location', $tool['description']);
        $this->assertArrayHasKey('parameters', $tool);
        $this->assertEquals('object', $tool['parameters']['type']);
        $this->assertArrayHasKey('properties', $tool['parameters']);
        $this->assertEquals(['location'], $tool['parameters']['required']);

        $properties = $tool['parameters']['properties'];
        $this->assertArrayHasKey('location', $properties);
        $this->assertArrayHasKey('units', $properties);
        $this->assertEquals('string', $properties['location']['type']);
        $this->assertEquals('The city name', $properties['location']['description']);
        $this->assertEquals(['celsius', 'fahrenheit'], $properties['units']['enum']);
    }

    public function testToolWithToolBuilderObject(): void
    {
        $weatherTool = ToolBuilder::create('get_weather')
            ->description('Get weather');
        $weatherTool->stringParam('city')->required();

        $result = $this->chatBuilder->tool($weatherTool);

        $this->assertSame($this->chatBuilder, $result);

        $tools = $this->chatBuilder->getTools();
        $this->assertCount(1, $tools);
        $this->assertEquals('get_weather', $tools[0]['name']);
        $this->assertEquals('Get weather', $tools[0]['description']);
    }

    public function testMultipleTools(): void
    {
        $this->chatBuilder
            ->tool('get_weather', function (ToolBuilder $builder) {
                $builder->stringParam('location')->required();
            })
            ->tool('send_email', function (ToolBuilder $builder) {
                $builder->stringParam('to')->required();
                $builder->stringParam('subject')->required();
                $builder->stringParam('body')->required();
            });

        $tools = $this->chatBuilder->getTools();
        $this->assertCount(2, $tools);
        $this->assertEquals('get_weather', $tools[0]['name']);
        $this->assertEquals('send_email', $tools[1]['name']);
    }

    public function testToolChoiceMethod(): void
    {
        $result = $this->chatBuilder->toolChoice('auto');

        $this->assertSame($this->chatBuilder, $result);
        $this->assertEquals('auto', $this->chatBuilder->getToolChoice());
    }

    public function testToArrayMinimal(): void
    {
        $this->chatBuilder
            ->model('gpt-3.5-turbo')
            ->user('Hello');

        $array = $this->chatBuilder->toArray();

        $expectedArray = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ]
        ];

        $this->assertEquals($expectedArray, $array);
    }

    public function testToArrayWithTemperature(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->temperature(0.8)
            ->user('Hello');

        $array = $this->chatBuilder->toArray();

        $this->assertArrayHasKey('temperature', $array);
        $this->assertEquals(0.8, $array['temperature']);
    }

    public function testToArrayWithMaxTokens(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->maxTokens(500)
            ->user('Hello');

        $array = $this->chatBuilder->toArray();

        $this->assertArrayHasKey('max_tokens', $array);
        $this->assertEquals(500, $array['max_tokens']);
    }

    public function testToArrayWithTools(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->user('What\'s the weather?')
            ->tool('get_weather', function (ToolBuilder $builder) {
                $builder->stringParam('location')->required();
            });

        $array = $this->chatBuilder->toArray();

        $this->assertArrayHasKey('tools', $array);
        $this->assertCount(1, $array['tools']);
        $this->assertEquals('get_weather', $array['tools'][0]['name']);
    }

    public function testToArrayWithToolChoice(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->user('Hello')
            ->toolChoice('none');

        $array = $this->chatBuilder->toArray();

        $this->assertArrayHasKey('tool_choice', $array);
        $this->assertEquals('none', $array['tool_choice']);
    }

    public function testToArrayComplete(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->temperature(0.5)
            ->maxTokens(2000)
            ->system('You are a weather assistant.')
            ->user('What is the weather in Paris?')
            ->tool('get_weather', function (ToolBuilder $builder) {
                $builder->description('Get weather information');
                $builder->stringParam('city')->required();
                $builder->stringParam('country');
            })
            ->toolChoice('auto');

        $array = $this->chatBuilder->toArray();

        $this->assertEquals('gpt-4', $array['model']);
        $this->assertEquals(0.5, $array['temperature']);
        $this->assertEquals(2000, $array['max_tokens']);
        $this->assertCount(2, $array['messages']);
        $this->assertCount(1, $array['tools']);
        $this->assertEquals('auto', $array['tool_choice']);
    }

    public function testToArrayWithoutOptionalFields(): void
    {
        $this->chatBuilder
            ->model('gpt-3.5-turbo')
            ->user('Hello');

        $array = $this->chatBuilder->toArray();

        $this->assertArrayNotHasKey('temperature', $array);
        $this->assertArrayNotHasKey('max_tokens', $array);
        $this->assertArrayNotHasKey('tools', $array);
        $this->assertArrayNotHasKey('tool_choice', $array);
    }

    public function testSendMethod(): void
    {
        $mockResponse = $this->createMock(LlmResponse::class);

        $this->mockLlmClient
            ->expects($this->once())
            ->method('send')
            ->with($this->chatBuilder)
            ->willReturn($mockResponse);

        $result = $this->chatBuilder->send();

        $this->assertSame($mockResponse, $result);
    }

    public function testFluentInterface(): void
    {
        $result = $this->chatBuilder
            ->model('gpt-4')
            ->temperature(0.7)
            ->maxTokens(1000)
            ->system('You are helpful.')
            ->user('Hello')
            ->assistant('Hi there!')
            ->tool('test_func', function (ToolBuilder $builder) {
                $builder->stringParam('param');
            })
            ->toolChoice('auto');

        $this->assertSame($this->chatBuilder, $result);

        $this->assertEquals('gpt-4', $this->chatBuilder->getModel());
        $this->assertEquals(0.7, $this->chatBuilder->getTemperature());
        $this->assertEquals(1000, $this->chatBuilder->getMaxTokens());
        $this->assertCount(3, $this->chatBuilder->getMessages());
        $this->assertCount(1, $this->chatBuilder->getTools());
        $this->assertEquals('auto', $this->chatBuilder->getToolChoice());
    }

    public function testGetters(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->temperature(0.9)
            ->maxTokens(500)
            ->system('System message')
            ->toolChoice('none');

        $this->assertEquals('gpt-4', $this->chatBuilder->getModel());
        $this->assertEquals(0.9, $this->chatBuilder->getTemperature());
        $this->assertEquals(500, $this->chatBuilder->getMaxTokens());
        $this->assertEquals('none', $this->chatBuilder->getToolChoice());

        $messages = $this->chatBuilder->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('System message', $messages[0]['content']);
    }

    public function testEmptyToolsArray(): void
    {
        $this->chatBuilder->model('gpt-3.5-turbo')->user('Hello');

        $tools = $this->chatBuilder->getTools();
        $this->assertEmpty($tools);

        $array = $this->chatBuilder->toArray();
        $this->assertArrayNotHasKey('tools', $array);
    }

    /**
     * @dataProvider temperatureValuesProvider
     */
    public function testTemperatureValues(float $temperature): void
    {
        $this->chatBuilder->temperature($temperature);

        $this->assertEquals($temperature, $this->chatBuilder->getTemperature());

        $array = $this->chatBuilder
            ->model('gpt-4')
            ->user('Test')
            ->toArray();

        $this->assertEquals($temperature, $array['temperature']);
    }

    public function temperatureValuesProvider(): array
    {
        return [
            'zero temperature' => [0.0],
            'low temperature' => [0.1],
            'medium temperature' => [0.5],
            'high temperature' => [0.9],
            'max temperature' => [1.0],
            'temperature above 1' => [1.5],
            'negative temperature' => [-0.1],
        ];
    }

    /**
     * @dataProvider toolChoiceModesProvider
     */
    public function testToolChoiceModes(string $mode): void
    {
        $this->chatBuilder->toolChoice($mode);

        $this->assertEquals($mode, $this->chatBuilder->getToolChoice());

        $array = $this->chatBuilder
            ->model('gpt-4')
            ->user('Test')
            ->toArray();

        $this->assertEquals($mode, $array['tool_choice']);
    }

    public function toolChoiceModesProvider(): array
    {
        return [
            'auto mode' => ['auto'],
            'none mode' => ['none'],
            'required mode' => ['required'],
        ];
    }

    public function testComplexConversation(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->temperature(0.7)
            ->system('You are a helpful assistant that can check weather and send emails.')
            ->user('What\'s the weather in London?')
            ->assistant('I\'ll check the weather in London for you.')
            ->user('Great! Also send an email to john@example.com about the weather.')
            ->tool('get_weather', function (ToolBuilder $builder) {
                $builder->description('Get current weather for a city');
                $builder->stringParam('city')->required()->description('City name');
                $builder->stringParam('country')->description('Country code');
                $builder->stringParam('units')->enum(['metric', 'imperial'])->description('Temperature units');
            })
            ->tool('send_email', function (ToolBuilder $builder) {
                $builder->description('Send an email to a recipient');
                $builder->stringParam('to')->required()->description('Recipient email address');
                $builder->stringParam('subject')->required()->description('Email subject');
                $builder->stringParam('body')->required()->description('Email body content');
                $builder->boolParam('urgent')->description('Mark email as urgent');
            })
            ->toolChoice('auto');

        $array = $this->chatBuilder->toArray();

        $this->assertEquals('gpt-4', $array['model']);
        $this->assertEquals(0.7, $array['temperature']);
        $this->assertEquals('auto', $array['tool_choice']);
        $this->assertCount(4, $array['messages']);
        $this->assertCount(2, $array['tools']);

        $this->assertEquals('system', $array['messages'][0]['role']);
        $this->assertEquals('user', $array['messages'][1]['role']);
        $this->assertEquals('assistant', $array['messages'][2]['role']);
        $this->assertEquals('user', $array['messages'][3]['role']);

        $weatherTool = $array['tools'][0];
        $emailTool = $array['tools'][1];

        $this->assertEquals('get_weather', $weatherTool['name']);
        $this->assertEquals('send_email', $emailTool['name']);
        $this->assertEquals(['city'], $weatherTool['parameters']['required']);
        $this->assertEquals(['to', 'subject', 'body'], $emailTool['parameters']['required']);
    }

    public function testMessageOrderMaintained(): void
    {
        $this->chatBuilder
            ->user('First message')
            ->assistant('Response to first')
            ->user('Second message')
            ->system('Late system message')
            ->assistant('Final response');

        $messages = $this->chatBuilder->getMessages();

        $expectedRoles = ['user', 'assistant', 'user', 'system', 'assistant'];
        $expectedContents = [
            'First message',
            'Response to first',
            'Second message',
            'Late system message',
            'Final response'
        ];

        $this->assertCount(5, $messages);

        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals($expectedRoles[$i], $messages[$i]['role']);
            $this->assertEquals($expectedContents[$i], $messages[$i]['content']);
        }
    }

    public function testOverwritingValues(): void
    {
        $this->chatBuilder
            ->model('gpt-3.5-turbo')
            ->temperature(0.5)
            ->maxTokens(100)
            ->toolChoice('none');

        $this->chatBuilder
            ->model('gpt-4')
            ->temperature(0.8)
            ->maxTokens(2000)
            ->toolChoice('auto');

        $this->assertEquals('gpt-4', $this->chatBuilder->getModel());
        $this->assertEquals(0.8, $this->chatBuilder->getTemperature());
        $this->assertEquals(2000, $this->chatBuilder->getMaxTokens());
        $this->assertEquals('auto', $this->chatBuilder->getToolChoice());

        $array = $this->chatBuilder->user('Test')->toArray();
        $this->assertEquals('gpt-4', $array['model']);
        $this->assertEquals(0.8, $array['temperature']);
        $this->assertEquals(2000, $array['max_tokens']);
        $this->assertEquals('auto', $array['tool_choice']);
    }
}
