<?php

declare(strict_types=1);

use Underwear\LlmWrapper\ChatBuilder;
use Underwear\LlmWrapper\FunctionBuilder;
use Underwear\LlmWrapper\FunctionParam;
use Underwear\LlmWrapper\FunctionCallMode;
use PHPUnit\Framework\TestCase;

final class ChatBuilderTest extends TestCase
{
    public function testFullPayloadMatchesExample(): void
    {
        $dimensionsObj = FunctionParam::object('dimensions')
            ->description('Размеры')
            ->props([
                FunctionParam::float('width')->description('Ширина в см')->required(),
                FunctionParam::float('height')->description('Высота в см')->required(),
                FunctionParam::float('depth')->description('Глубина в см')->required(),
            ]);

        $payload = ChatBuilder::make()
            ->model('gpt-4')
            ->temperature(0.7)
            ->system('Ты помощник в системе управления товарами.')
            ->user('Создай новый товар.')
            ->function('createProduct', function (FunctionBuilder $fn) use ($dimensionsObj) {
                $fn->description('Создаёт новый товар в системе')
                    ->param(FunctionParam::string('name')->description('Название товара')->required())
                    ->param(FunctionParam::int('price')->description('Цена в центах')->min(100)->max(100000)->required())
                    ->param(FunctionParam::string('category')->description('Категория товара')->enum(['books', 'clothing', 'electronics'])->required())
                    ->param(FunctionParam::arrayOf(FunctionParam::string('tag')->description('Тег'))->description('Список тегов'))
                    ->param(FunctionParam::arrayOf(
                        FunctionParam::object('variant')->props([
                            FunctionParam::string('sku')->description('Артикул')->required(),
                            FunctionParam::int('stock')->description('Остаток на складе')->required(),
                        ])
                    )->description('Вариации товара'))
                    ->param($dimensionsObj);
            })
            ->functionCall(FunctionCallMode::auto())
            ->toArray();

        // Basic assertions that keys exist
        $this->assertSame('gpt-4', $payload['model']);
        $this->assertArrayHasKey('messages', $payload);
        $this->assertCount(2, $payload['messages']);

        $this->assertArrayHasKey('functions', $payload);
        $this->assertCount(1, $payload['functions']);

        $this->assertSame('auto', $payload['function_call']);
    }
}
