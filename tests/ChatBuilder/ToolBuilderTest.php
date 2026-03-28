<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\Tests\ChatBuilder;

use PHPUnit\Framework\TestCase;
use Underwear\LlmWrapper\ChatBuilder\ToolBuilder;
use Underwear\LlmWrapper\ChatBuilder\FunctionParam;
use InvalidArgumentException;

class ToolBuilderTest extends TestCase
{
    public function testConstructorWithValidName(): void
    {
        $builder = new ToolBuilder('test_function');

        $this->assertEquals('test_function', $builder->getName());
        $this->assertNull($builder->getDescription());
        $this->assertEmpty($builder->getParameters());
    }

    public function testStaticCreateMethod(): void
    {
        $builder = ToolBuilder::create('search_users');

        $this->assertInstanceOf(ToolBuilder::class, $builder);
        $this->assertEquals('search_users', $builder->getName());
    }

    /**
     * @dataProvider invalidToolNamesProvider
     */
    public function testConstructorWithInvalidName(string $invalidName, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new ToolBuilder($invalidName);
    }

    public function invalidToolNamesProvider(): array
    {
        return [
            'empty name' => ['', 'Tool name cannot be empty'],
            'starts with number' => ['123invalid', 'Tool name must be a valid identifier'],
            'contains spaces' => ['invalid name', 'Tool name must be a valid identifier'],
            'contains special chars' => ['invalid-name', 'Tool name must be a valid identifier'],
            'too long' => [str_repeat('a', 65), 'Tool name cannot exceed 64 characters'],
        ];
    }

    public function testValidToolNames(): void
    {
        $validNames = ['validName', 'valid_name', '_validName', 'validName123', 'a'];

        foreach ($validNames as $name) {
            $builder = new ToolBuilder($name);
            $this->assertEquals($name, $builder->getName());
        }
    }

    public function testDescriptionMethod(): void
    {
        $builder = ToolBuilder::create('test')
            ->description('Test function description');

        $this->assertEquals('Test function description', $builder->getDescription());
    }

    public function testFluentApiChaining(): void
    {
        $builder = ToolBuilder::create('test')
            ->description('Test description');

        $this->assertInstanceOf(ToolBuilder::class, $builder);
        $this->assertEquals('test', $builder->getName());
        $this->assertEquals('Test description', $builder->getDescription());
    }

    public function testParamMethod(): void
    {
        $param = FunctionParam::string('username');
        $builder = ToolBuilder::create('test')->param($param);

        $parameters = $builder->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertSame($param, $parameters[0]);
    }

    public function testDuplicateParamThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter 'username' already exists");

        ToolBuilder::create('test')
            ->param(FunctionParam::string('username'))
            ->param(FunctionParam::string('username'));
    }

    public function testParamsMethod(): void
    {
        $param1 = FunctionParam::string('param1');
        $param2 = FunctionParam::int('param2');

        $builder = ToolBuilder::create('test')->params([$param1, $param2]);

        $this->assertCount(2, $builder->getParameters());
    }

    public function testParamsMethodWithInvalidItem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All items in params array must be FunctionParam instances');

        ToolBuilder::create('test')->params(['not_a_param']);
    }

    public function testStringParamShortcut(): void
    {
        $builder = ToolBuilder::create('test');
        $param = $builder->stringParam('username');

        $this->assertInstanceOf(FunctionParam::class, $param);
        $this->assertEquals('username', $param->getName());
        $this->assertEquals('string', $param->getType());
        $this->assertTrue($builder->hasParam('username'));
    }

    public function testIntParamShortcut(): void
    {
        $builder = ToolBuilder::create('test');
        $param = $builder->intParam('age');

        $this->assertInstanceOf(FunctionParam::class, $param);
        $this->assertEquals('age', $param->getName());
        $this->assertEquals('integer', $param->getType());
    }

    public function testFloatParamShortcut(): void
    {
        $builder = ToolBuilder::create('test');
        $param = $builder->floatParam('price');

        $this->assertEquals('number', $param->getType());
    }

    public function testBoolParamShortcut(): void
    {
        $builder = ToolBuilder::create('test');
        $param = $builder->boolParam('active');

        $this->assertEquals('boolean', $param->getType());
    }

    public function testObjectParamShortcut(): void
    {
        $builder = ToolBuilder::create('test');
        $param = $builder->objectParam('config');

        $this->assertEquals('object', $param->getType());
    }

    public function testArrayParamShortcut(): void
    {
        $builder = ToolBuilder::create('test');
        $param = $builder->arrayParam('items');

        $this->assertEquals('array', $param->getType());
    }

    public function testParameterShortcutsChaining(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('name')->required()->description('User name');
        $builder->intParam('age')->min(0)->max(120);

        $this->assertCount(2, $builder->getParameters());
        $this->assertTrue($builder->hasParam('name'));
        $this->assertTrue($builder->hasParam('age'));
    }

    public function testHasParam(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('username');

        $this->assertTrue($builder->hasParam('username'));
        $this->assertFalse($builder->hasParam('nonexistent'));
    }

    public function testGetParam(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('username');

        $param = $builder->getParam('username');
        $this->assertInstanceOf(FunctionParam::class, $param);
        $this->assertEquals('username', $param->getName());

        $this->assertNull($builder->getParam('nonexistent'));
    }

    public function testRemoveParam(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('username');
        $builder->intParam('age');

        $this->assertCount(2, $builder->getParameters());

        $builder->removeParam('username');

        $this->assertCount(1, $builder->getParameters());
        $this->assertFalse($builder->hasParam('username'));
        $this->assertTrue($builder->hasParam('age'));
    }

    public function testGetRequiredParams(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('required')->required();
        $builder->stringParam('optional');
        $builder->intParam('another_required')->required();

        $requiredParams = $builder->getRequiredParams();

        $this->assertCount(2, $requiredParams);
        $this->assertArrayHasKey('required', $requiredParams);
        $this->assertArrayHasKey('another_required', $requiredParams);
    }

    public function testGetOptionalParams(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('required')->required();
        $builder->stringParam('optional');
        $builder->intParam('another_optional');

        $optionalParams = $builder->getOptionalParams();

        $this->assertCount(2, $optionalParams);
        $this->assertArrayHasKey('optional', $optionalParams);
        $this->assertArrayHasKey('another_optional', $optionalParams);
    }

    public function testValidateEmptyTool(): void
    {
        $builder = ToolBuilder::create('test');
        $errors = $builder->validate();

        $this->assertContains('Tool must have at least one parameter', $errors);
        $this->assertFalse($builder->isValid());
    }

    public function testValidateValidTool(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('param');

        $errors = $builder->validate();

        $this->assertEmpty($errors);
        $this->assertTrue($builder->isValid());
    }

    public function testToSchemaBasic(): void
    {
        $builder = ToolBuilder::create('search_users')
            ->description('Search for users');
        $builder->stringParam('query')->required();
        $builder->intParam('limit')->min(1)->max(100);

        $schema = $builder->toSchema();

        $this->assertEquals('search_users', $schema['name']);
        $this->assertEquals('Search for users', $schema['description']);
        $this->assertEquals('object', $schema['parameters']['type']);
        $this->assertArrayHasKey('properties', $schema['parameters']);
        $this->assertEquals(['query'], $schema['parameters']['required']);

        $this->assertArrayHasKey('query', $schema['parameters']['properties']);
        $this->assertArrayHasKey('limit', $schema['parameters']['properties']);
        $this->assertEquals('string', $schema['parameters']['properties']['query']['type']);
        $this->assertEquals('integer', $schema['parameters']['properties']['limit']['type']);
    }

    public function testToSchemaWithoutDescription(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('param');

        $schema = $builder->toSchema();

        $this->assertArrayNotHasKey('description', $schema);
    }

    public function testToSchemaWithoutRequiredParams(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('optional1');
        $builder->stringParam('optional2');

        $schema = $builder->toSchema();

        $this->assertArrayNotHasKey('required', $schema['parameters']);
    }

    public function testToArrayMethod(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('param');

        $this->assertEquals($builder->toSchema(), $builder->toArray());
    }

    public function testToJsonMethod(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('param');

        $json = $builder->toJson();
        $decoded = json_decode($json, true);

        $this->assertEquals($builder->toSchema(), $decoded);
        $this->assertJson($json);
    }

    public function testCloneWithName(): void
    {
        $original = ToolBuilder::create('original')
            ->description('Original function');
        $original->stringParam('param1')->required();
        $original->intParam('param2');

        $clone = $original->cloneWithName('cloned');

        $this->assertEquals('cloned', $clone->getName());
        $this->assertEquals('Original function', $clone->getDescription());
        $this->assertCount(2, $clone->getParameters());
        $this->assertTrue($clone->hasParam('param1'));
        $this->assertTrue($clone->hasParam('param2'));

        $this->assertNotSame($original, $clone);
    }

    public function testGetParametersAssoc(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('param1');
        $builder->intParam('param2');

        $assocParams = $builder->getParametersAssoc();

        $this->assertIsArray($assocParams);
        $this->assertArrayHasKey('param1', $assocParams);
        $this->assertArrayHasKey('param2', $assocParams);
        $this->assertInstanceOf(FunctionParam::class, $assocParams['param1']);
    }

    public function testGetParameterCount(): void
    {
        $builder = ToolBuilder::create('test');

        $this->assertEquals(0, $builder->getParameterCount());

        $builder->stringParam('param1');
        $this->assertEquals(1, $builder->getParameterCount());

        $builder->intParam('param2');
        $this->assertEquals(2, $builder->getParameterCount());
    }

    public function testGetParameterNames(): void
    {
        $builder = ToolBuilder::create('test');
        $builder->stringParam('username');
        $builder->intParam('age');
        $builder->boolParam('active');

        $names = $builder->getParameterNames();

        $this->assertEquals(['username', 'age', 'active'], $names);
    }

    public function testToStringMethod(): void
    {
        $builder = ToolBuilder::create('test_function');
        $builder->stringParam('param1')->required();
        $builder->stringParam('param2');
        $builder->intParam('param3')->required();

        $string = (string) $builder;

        $this->assertStringContainsString('test_function', $string);
        $this->assertStringContainsString('params: 3', $string);
        $this->assertStringContainsString('required: 2', $string);
    }

    public function testComplexToolWithNestedObjects(): void
    {
        $builder = ToolBuilder::create('create_user')
            ->description('Creates a new user account');

        $builder->objectParam('user_data')
            ->required()
            ->description('User information')
            ->props([
                'email' => FunctionParam::string('email')->required(),
                'name' => FunctionParam::string('name')->required(),
                'age' => FunctionParam::int('age')->min(13)->max(120),
                'preferences' => FunctionParam::object('preferences')
                    ->props([
                        'theme' => FunctionParam::string('theme')->enum(['light', 'dark']),
                        'notifications' => FunctionParam::bool('notifications')
                    ])
            ]);

        $builder->arrayParam('roles')
            ->description('User roles')
            ->arrayOf(FunctionParam::string('role'));

        $schema = $builder->toSchema();

        $this->assertEquals('create_user', $schema['name']);
        $this->assertEquals('Creates a new user account', $schema['description']);
        $this->assertEquals(['user_data'], $schema['parameters']['required']);

        $userData = $schema['parameters']['properties']['user_data'];
        $this->assertEquals('object', $userData['type']);
        $this->assertEquals('User information', $userData['description']);
        $this->assertEquals(['email', 'name'], $userData['required']);

        $preferences = $userData['properties']['preferences'];
        $this->assertEquals('object', $preferences['type']);
        $this->assertArrayHasKey('theme', $preferences['properties']);
        $this->assertEquals(['light', 'dark'], $preferences['properties']['theme']['enum']);

        $roles = $schema['parameters']['properties']['roles'];
        $this->assertEquals('array', $roles['type']);
        $this->assertEquals('User roles', $roles['description']);
        $this->assertEquals(['type' => 'string'], $roles['items']);
    }

    /**
     * @dataProvider toolBuilderScenariosProvider
     */
    public function testVariousScenarios(string $name, callable $builderSetup, array $expectedChecks): void
    {
        $builder = ToolBuilder::create($name);
        $builderSetup($builder);

        foreach ($expectedChecks as $check => $expected) {
            switch ($check) {
                case 'paramCount':
                    $this->assertEquals($expected, $builder->getParameterCount());
                    break;
                case 'requiredCount':
                    $this->assertEquals($expected, count($builder->getRequiredParams()));
                    break;
                case 'isValid':
                    $this->assertEquals($expected, $builder->isValid());
                    break;
                case 'hasParam':
                    foreach ($expected as $paramName => $exists) {
                        $this->assertEquals($exists, $builder->hasParam($paramName));
                    }
                    break;
            }
        }
    }

    public function toolBuilderScenariosProvider(): array
    {
        return [
            'simple tool' => [
                'simple',
                function($b) {
                    $b->stringParam('query')->required();
                },
                [
                    'paramCount' => 1,
                    'requiredCount' => 1,
                    'isValid' => true,
                    'hasParam' => ['query' => true, 'nonexistent' => false]
                ]
            ],
            'complex tool' => [
                'complex',
                function($b) {
                    $b->stringParam('param1')->required();
                    $b->intParam('param2');
                    $b->boolParam('param3')->required();
                    $b->arrayParam('param4');
                },
                [
                    'paramCount' => 4,
                    'requiredCount' => 2,
                    'isValid' => true
                ]
            ],
            'no parameters' => [
                'empty',
                function($b) {
                    // no params
                },
                [
                    'paramCount' => 0,
                    'requiredCount' => 0,
                    'isValid' => false
                ]
            ]
        ];
    }
}
