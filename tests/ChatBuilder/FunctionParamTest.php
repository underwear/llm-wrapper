<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\Tests\ChatBuilder;

use PHPUnit\Framework\TestCase;
use Underwear\LlmWrapper\ChatBuilder\FunctionParam;
use InvalidArgumentException;

class FunctionParamTest extends TestCase
{
    public function testStringCreation(): void
    {
        $param = FunctionParam::string('username');

        $this->assertEquals('username', $param->getName());
        $this->assertEquals('string', $param->getType());
        $this->assertFalse($param->isRequired());
    }

    public function testIntCreation(): void
    {
        $param = FunctionParam::int('age');

        $this->assertEquals('age', $param->getName());
        $this->assertEquals('integer', $param->getType());
    }

    public function testFloatCreation(): void
    {
        $param = FunctionParam::float('price');

        $this->assertEquals('price', $param->getName());
        $this->assertEquals('number', $param->getType());
    }

    public function testBoolCreation(): void
    {
        $param = FunctionParam::bool('isActive');

        $this->assertEquals('isActive', $param->getName());
        $this->assertEquals('boolean', $param->getType());
    }

    public function testObjectCreation(): void
    {
        $param = FunctionParam::object('user');

        $this->assertEquals('user', $param->getName());
        $this->assertEquals('object', $param->getType());
    }

    public function testArrayCreation(): void
    {
        $param = FunctionParam::array('items');

        $this->assertEquals('items', $param->getName());
        $this->assertEquals('array', $param->getType());
    }

    public function testFluentApiChaining(): void
    {
        $param = FunctionParam::string('email')
            ->description('User email address')
            ->required()
            ->nullable();

        $this->assertEquals('email', $param->getName());
        $this->assertTrue($param->isRequired());

        $schema = $param->toSchema();
        $this->assertEquals('User email address', $schema['description']);
        $this->assertEquals(['string', 'null'], $schema['type']);
    }

    public function testEnumValues(): void
    {
        $param = FunctionParam::string('status')
            ->enum(['active', 'inactive', 'pending']);

        $schema = $param->toSchema();
        $this->assertEquals(['active', 'inactive', 'pending'], $schema['enum']);
    }

    public function testStringMinMaxLength(): void
    {
        $param = FunctionParam::string('password')
            ->min(8)
            ->max(64);

        $schema = $param->toSchema();
        $this->assertEquals(8, $schema['minLength']);
        $this->assertEquals(64, $schema['maxLength']);
    }

    public function testNumberMinMax(): void
    {
        $param = FunctionParam::int('score')
            ->min(0)
            ->max(100);

        $schema = $param->toSchema();
        $this->assertEquals(0, $schema['minimum']);
        $this->assertEquals(100, $schema['maximum']);
    }

    public function testArrayOfString(): void
    {
        $param = FunctionParam::array('tags')
            ->arrayOf(FunctionParam::string('tag'))
            ->minItems(1)
            ->maxItems(10);

        $schema = $param->toSchema();
        $this->assertEquals('array', $schema['type']);
        $this->assertEquals(['type' => 'string'], $schema['items']);
        $this->assertEquals(1, $schema['minItems']);
        $this->assertEquals(10, $schema['maxItems']);
    }

    public function testArrayOfException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('arrayOf() can only be used with array type');

        FunctionParam::string('name')->arrayOf(FunctionParam::string('item'));
    }

    public function testMinItemsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minItems() can only be used with array type');

        FunctionParam::string('name')->minItems(1);
    }

    public function testMaxItemsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxItems() can only be used with array type');

        FunctionParam::string('name')->maxItems(10);
    }

    public function testObjectWithProperties(): void
    {
        $param = FunctionParam::object('user')
            ->props([
                'name' => FunctionParam::string('name')->required(),
                'age' => FunctionParam::int('age')->min(0)->max(120),
                'email' => FunctionParam::string('email')->nullable()
            ]);

        $schema = $param->toSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertEquals(['name'], $schema['required']);

        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('integer', $schema['properties']['age']['type']);
        $this->assertEquals(['string', 'null'], $schema['properties']['email']['type']);
        $this->assertEquals(0, $schema['properties']['age']['minimum']);
        $this->assertEquals(120, $schema['properties']['age']['maximum']);
    }

    public function testObjectAddProp(): void
    {
        $param = FunctionParam::object('person')
            ->addProp(FunctionParam::string('firstName')->required())
            ->addProp(FunctionParam::string('lastName')->required())
            ->addProp(FunctionParam::int('age')->min(0));

        $schema = $param->toSchema();

        $this->assertCount(3, $schema['properties']);
        $this->assertEquals(['firstName', 'lastName'], $schema['required']);
    }

    public function testPropsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('props() can only be used with object type');

        FunctionParam::string('name')->props([]);
    }

    public function testAddPropException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('addProp() can only be used with object type');

        FunctionParam::string('name')->addProp(FunctionParam::string('prop'));
    }

    public function testStringArrayShortcut(): void
    {
        $param = FunctionParam::stringArray('tags')
            ->minItems(1)
            ->maxItems(5);

        $schema = $param->toSchema();

        $this->assertEquals('array', $schema['type']);
        $this->assertEquals(['type' => 'string'], $schema['items']);
        $this->assertEquals(1, $schema['minItems']);
        $this->assertEquals(5, $schema['maxItems']);
    }

    public function testIntArrayShortcut(): void
    {
        $param = FunctionParam::intArray('scores');

        $schema = $param->toSchema();

        $this->assertEquals('array', $schema['type']);
        $this->assertEquals(['type' => 'integer'], $schema['items']);
    }

    public function testObjectArrayShortcut(): void
    {
        $param = FunctionParam::objectArray('users', [
            'name' => FunctionParam::string('name')->required(),
            'age' => FunctionParam::int('age')
        ]);

        $schema = $param->toSchema();

        $this->assertEquals('array', $schema['type']);
        $this->assertEquals('object', $schema['items']['type']);
        $this->assertArrayHasKey('properties', $schema['items']);
        $this->assertEquals(['name'], $schema['items']['required']);
    }

    public function testComplexNestedStructure(): void
    {
        $param = FunctionParam::object('config')
            ->description('Application configuration')
            ->props([
                'database' => FunctionParam::object('database')
                    ->props([
                        'host' => FunctionParam::string('host')->required(),
                        'port' => FunctionParam::int('port')->min(1)->max(65535)->required(),
                        'credentials' => FunctionParam::object('credentials')
                            ->props([
                                'username' => FunctionParam::string('username')->required(),
                                'password' => FunctionParam::string('password')->required()
                            ])
                            ->required()
                    ])
                    ->required(),
                'features' => FunctionParam::stringArray('features')
                    ->description('List of enabled features'),
                'servers' => FunctionParam::objectArray('servers', [
                    'name' => FunctionParam::string('name')->required(),
                    'url' => FunctionParam::string('url')->required(),
                    'timeout' => FunctionParam::int('timeout')->min(1)->max(300)
                ])
            ])
            ->required();

        $schema = $param->toSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertEquals('Application configuration', $schema['description']);
        $this->assertEquals(['database'], $schema['required']);

        $database = $schema['properties']['database'];
        $this->assertEquals('object', $database['type']);
        $this->assertEquals(['host', 'port', 'credentials'], $database['required']);

        $credentials = $database['properties']['credentials'];
        $this->assertEquals('object', $credentials['type']);
        $this->assertEquals(['username', 'password'], $credentials['required']);

        $features = $schema['properties']['features'];
        $this->assertEquals('array', $features['type']);
        $this->assertEquals(['type' => 'string'], $features['items']);

        $servers = $schema['properties']['servers'];
        $this->assertEquals('array', $servers['type']);
        $this->assertEquals('object', $servers['items']['type']);
        $this->assertEquals(['name', 'url'], $servers['items']['required']);
    }

    public function testNullableType(): void
    {
        $param = FunctionParam::string('optional')->nullable();

        $schema = $param->toSchema();
        $this->assertEquals(['string', 'null'], $schema['type']);
    }

    public function testEmptyObjectSchema(): void
    {
        $param = FunctionParam::object('empty');

        $schema = $param->toSchema();
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayNotHasKey('properties', $schema);
        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testEmptyArraySchema(): void
    {
        $param = FunctionParam::array('empty');

        $schema = $param->toSchema();
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayNotHasKey('items', $schema);
    }

    public function testToSchemaWithoutOptionalFields(): void
    {
        $param = FunctionParam::string('simple');

        $schema = $param->toSchema();
        $this->assertEquals(['type' => 'string'], $schema);
        $this->assertArrayNotHasKey('description', $schema);
        $this->assertArrayNotHasKey('enum', $schema);
        $this->assertArrayNotHasKey('minLength', $schema);
        $this->assertArrayNotHasKey('maxLength', $schema);
    }

    /**
     * @dataProvider validationDataProvider
     */
    public function testValidationScenarios(FunctionParam $param, array $expectedSchema): void
    {
        $this->assertEquals($expectedSchema, $param->toSchema());
    }

    public function validationDataProvider(): array
    {
        return [
            'required string with enum' => [
                FunctionParam::string('status')->required()->enum(['active', 'inactive']),
                [
                    'type' => 'string',
                    'enum' => ['active', 'inactive']
                ]
            ],
            'nullable number with range' => [
                FunctionParam::float('rating')->nullable()->min(1)->max(5),
                [
                    'type' => ['number', 'null'],
                    'minimum' => 1,
                    'maximum' => 5
                ]
            ],
            'array of integers with size limits' => [
                FunctionParam::intArray('numbers')->minItems(2)->maxItems(10),
                [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'minItems' => 2,
                    'maxItems' => 10
                ]
            ]
        ];
    }
}
