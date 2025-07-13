<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\Tests\ChatBuilder;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Underwear\LlmWrapper\ChatBuilder\ChatBuilder;
use Underwear\LlmWrapper\ChatBuilder\FunctionBuilder;
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
        $this->assertEmpty($chatBuilder->getFunctions());
        $this->assertNull($chatBuilder->getFunctionCallMode());
    }

    public function testModelMethod(): void
    {
        $result = $this->chatBuilder->model('gpt-4');

        $this->assertSame($this->chatBuilder, $result); // fluent interface
        $this->assertEquals('gpt-4', $this->chatBuilder->getModel());
    }

    public function testTemperatureMethod(): void
    {
        $result = $this->chatBuilder->temperature(0.7);

        $this->assertSame($this->chatBuilder, $result); // fluent interface
        $this->assertEquals(0.7, $this->chatBuilder->getTemperature());
    }

    public function testSystemMessage(): void
    {
        $content = 'You are a helpful assistant.';
        $result = $this->chatBuilder->system($content);

        $this->assertSame($this->chatBuilder, $result); // fluent interface

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

        $this->assertSame($this->chatBuilder, $result); // fluent interface

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

        $this->assertSame($this->chatBuilder, $result); // fluent interface

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

    public function testFunctionMethod(): void
    {
        $result = $this->chatBuilder->function('get_weather', function (FunctionBuilder $builder) {
            $builder->description('Get current weather for a location');
            $builder->stringParam('location')->required()->description('The city name');
            $builder->stringParam('units')->enum(['celsius', 'fahrenheit']);
        });

        $this->assertSame($this->chatBuilder, $result); // fluent interface

        $functions = $this->chatBuilder->getFunctions();
        $this->assertCount(1, $functions);

        $function = $functions[0];
        $this->assertEquals('get_weather', $function['name']);
        $this->assertEquals('Get current weather for a location', $function['description']);
        $this->assertArrayHasKey('parameters', $function);
        $this->assertEquals('object', $function['parameters']['type']);
        $this->assertArrayHasKey('properties', $function['parameters']);
        $this->assertEquals(['location'], $function['parameters']['required']);

        // Check parameter properties
        $properties = $function['parameters']['properties'];
        $this->assertArrayHasKey('location', $properties);
        $this->assertArrayHasKey('units', $properties);
        $this->assertEquals('string', $properties['location']['type']);
        $this->assertEquals('The city name', $properties['location']['description']);
        $this->assertEquals(['celsius', 'fahrenheit'], $properties['units']['enum']);
    }

    public function testMultipleFunctions(): void
    {
        $this->chatBuilder
            ->function('get_weather', function (FunctionBuilder $builder) {
                $builder->stringParam('location')->required();
            })
            ->function('send_email', function (FunctionBuilder $builder) {
                $builder->stringParam('to')->required();
                $builder->stringParam('subject')->required();
                $builder->stringParam('body')->required();
            });

        $functions = $this->chatBuilder->getFunctions();
        $this->assertCount(2, $functions);
        $this->assertEquals('get_weather', $functions[0]['name']);
        $this->assertEquals('send_email', $functions[1]['name']);
    }

    public function testFunctionCallMethod(): void
    {
        $result = $this->chatBuilder->functionCall('auto');

        $this->assertSame($this->chatBuilder, $result); // fluent interface
        $this->assertEquals('auto', $this->chatBuilder->getFunctionCallMode());
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

    public function testToArrayWithFunctions(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->user('What\'s the weather?')
            ->function('get_weather', function (FunctionBuilder $builder) {
                $builder->stringParam('location')->required();
            });

        $array = $this->chatBuilder->toArray();

        $this->assertArrayHasKey('functions', $array);
        $this->assertCount(1, $array['functions']);
        $this->assertEquals('get_weather', $array['functions'][0]['name']);
    }

    public function testToArrayWithFunctionCallMode(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->user('Hello')
            ->functionCall('none');

        $array = $this->chatBuilder->toArray();

        $this->assertArrayHasKey('function_call', $array);
        $this->assertEquals('none', $array['function_call']);
    }

    public function testToArrayComplete(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->temperature(0.5)
            ->system('You are a weather assistant.')
            ->user('What is the weather in Paris?')
            ->function('get_weather', function (FunctionBuilder $builder) {
                $builder->description('Get weather information');
                $builder->stringParam('city')->required();
                $builder->stringParam('country');
            })
            ->functionCall('auto');

        $array = $this->chatBuilder->toArray();

        $this->assertEquals('gpt-4', $array['model']);
        $this->assertEquals(0.5, $array['temperature']);
        $this->assertCount(2, $array['messages']);
        $this->assertCount(1, $array['functions']);
        $this->assertEquals('auto', $array['function_call']);
    }

    public function testToArrayWithoutOptionalFields(): void
    {
        $this->chatBuilder
            ->model('gpt-3.5-turbo')
            ->user('Hello');

        $array = $this->chatBuilder->toArray();

        $this->assertArrayNotHasKey('temperature', $array);
        $this->assertArrayNotHasKey('functions', $array);
        $this->assertArrayNotHasKey('function_call', $array);
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
            ->system('You are helpful.')
            ->user('Hello')
            ->assistant('Hi there!')
            ->function('test_func', function (FunctionBuilder $builder) {
                $builder->stringParam('param');
            })
            ->functionCall('auto');

        $this->assertSame($this->chatBuilder, $result);

        // Verify all data was set correctly
        $this->assertEquals('gpt-4', $this->chatBuilder->getModel());
        $this->assertEquals(0.7, $this->chatBuilder->getTemperature());
        $this->assertCount(3, $this->chatBuilder->getMessages());
        $this->assertCount(1, $this->chatBuilder->getFunctions());
        $this->assertEquals('auto', $this->chatBuilder->getFunctionCallMode());
    }

    public function testGetters(): void
    {
        $this->chatBuilder
            ->model('gpt-4')
            ->temperature(0.9)
            ->system('System message')
            ->functionCall('none');

        $this->assertEquals('gpt-4', $this->chatBuilder->getModel());
        $this->assertEquals(0.9, $this->chatBuilder->getTemperature());
        $this->assertEquals('none', $this->chatBuilder->getFunctionCallMode());

        $messages = $this->chatBuilder->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals('system', $messages[0]['role']);
        $this->assertEquals('System message', $messages[0]['content']);
    }

    public function testEmptyFunctionsArray(): void
    {
        $this->chatBuilder->model('gpt-3.5-turbo')->user('Hello');

        $functions = $this->chatBuilder->getFunctions();
        $this->assertEmpty($functions);

        $array = $this->chatBuilder->toArray();
        $this->assertArrayNotHasKey('functions', $array);
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
     * @dataProvider functionCallModesProvider
     */
    public function testFunctionCallModes(string $mode): void
    {
        $this->chatBuilder->functionCall($mode);

        $this->assertEquals($mode, $this->chatBuilder->getFunctionCallMode());

        $array = $this->chatBuilder
            ->model('gpt-4')
            ->user('Test')
            ->toArray();

        $this->assertEquals($mode, $array['function_call']);
    }

    public function functionCallModesProvider(): array
    {
        return [
            'auto mode' => ['auto'],
            'none mode' => ['none'],
            'specific function' => ['{"name": "get_weather"}'],
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
            ->function('get_weather', function (FunctionBuilder $builder) {
                $builder->description('Get current weather for a city');
                $builder->stringParam('city')->required()->description('City name');
                $builder->stringParam('country')->description('Country code');
                $builder->stringParam('units')->enum(['metric', 'imperial'])->description('Temperature units');
            })
            ->function('send_email', function (FunctionBuilder $builder) {
                $builder->description('Send an email to a recipient');
                $builder->stringParam('to')->required()->description('Recipient email address');
                $builder->stringParam('subject')->required()->description('Email subject');
                $builder->stringParam('body')->required()->description('Email body content');
                $builder->boolParam('urgent')->description('Mark email as urgent');
            })
            ->functionCall('auto');

        $array = $this->chatBuilder->toArray();

        // Verify structure
        $this->assertEquals('gpt-4', $array['model']);
        $this->assertEquals(0.7, $array['temperature']);
        $this->assertEquals('auto', $array['function_call']);
        $this->assertCount(4, $array['messages']);
        $this->assertCount(2, $array['functions']);

        // Verify message sequence
        $this->assertEquals('system', $array['messages'][0]['role']);
        $this->assertEquals('user', $array['messages'][1]['role']);
        $this->assertEquals('assistant', $array['messages'][2]['role']);
        $this->assertEquals('user', $array['messages'][3]['role']);

        // Verify functions
        $weatherFunction = $array['functions'][0];
        $emailFunction = $array['functions'][1];

        $this->assertEquals('get_weather', $weatherFunction['name']);
        $this->assertEquals('send_email', $emailFunction['name']);
        $this->assertEquals(['city'], $weatherFunction['parameters']['required']);
        $this->assertEquals(['to', 'subject', 'body'], $emailFunction['parameters']['required']);
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
            ->functionCall('none');

        // Overwrite values
        $this->chatBuilder
            ->model('gpt-4')
            ->temperature(0.8)
            ->functionCall('auto');

        // Verify latest values are used
        $this->assertEquals('gpt-4', $this->chatBuilder->getModel());
        $this->assertEquals(0.8, $this->chatBuilder->getTemperature());
        $this->assertEquals('auto', $this->chatBuilder->getFunctionCallMode());

        $array = $this->chatBuilder->user('Test')->toArray();
        $this->assertEquals('gpt-4', $array['model']);
        $this->assertEquals(0.8, $array['temperature']);
        $this->assertEquals('auto', $array['function_call']);
    }
}