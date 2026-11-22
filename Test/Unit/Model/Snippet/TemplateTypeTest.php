<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The type of a template's root, read and written without disturbing the rest of the template
 */
class TemplateTypeTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('readTypes')]
    public function testTheTypesOfTheRootAreRead(string $template, array $expected): void
    {
        self::assertSame($expected, (new TemplateType())->typesOf($template));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function readTypes(): array
    {
        return [
            'a string' => ['{"@type": "Product"}', ['Product']],
            'a list' => ['{"@type": ["Product", "Car"]}', ['Product', 'Car']],
            'none' => ['{"name": "x"}', []],
            'an empty list' => ['{"@type": []}', []],
            'a list with an object' => ['{"@type": ["Product", {"x": 1}]}', []],
            'a number' => ['{"@type": 5}', []],
            'a nested type only' => ['{"offers": {"@type": "Offer"}}', []],
            'a list root' => ['[{"@type": "Product"}]', []],
            'not JSON' => ['{"@type": "Product"', []],
            'empty' => ['', []],
        ];
    }

    public function testAStringTypeIsReplacedWhereItIs(): void
    {
        $written = (new TemplateType())->withType(
            '{"@context": "https://schema.org", "name": "{{ product.name }}", "@type": "Product"}',
            'ProductGroup'
        );

        self::assertSame(
            ['@context' => 'https://schema.org', 'name' => '{{ product.name }}', '@type' => 'ProductGroup'],
            json_decode($written, true)
        );
    }

    public function testTheFirstEntryOfAListIsReplacedAndTheOthersKept(): void
    {
        $written = (new TemplateType())->withType('{"@type": ["Product", "Car"]}', 'Vehicle');

        self::assertSame(['@type' => ['Vehicle', 'Car']], json_decode($written, true));
    }

    public function testARootWithoutATypeGetsOneAfterItsContext(): void
    {
        $written = (new TemplateType())->withType('{"@context": "https://schema.org", "name": "x"}', 'Thing');

        self::assertSame(['@context', '@type', 'name'], array_keys((array) json_decode($written, true)));
    }

    public function testARootWithoutAContextGetsTheTypeFirst(): void
    {
        $written = (new TemplateType())->withType('{"name": "x"}', 'Thing');

        self::assertSame(['@type', 'name'], array_keys((array) json_decode($written, true)));
    }

    public function testEmptyObjectsSlashesUnicodeAndFractionsSurviveTheRewrite(): void
    {
        $written = (new TemplateType())->withType(
            '{"@type": "Product", "url": "https://example.com/a", "brand": {}, "name": "Сумка",'
                . ' "ratio": 1.0, "tags": []}',
            'Product'
        );

        self::assertStringContainsString('"brand": {}', $written);
        self::assertStringContainsString('"tags": []', $written);
        self::assertStringContainsString('https://example.com/a', $written);
        self::assertStringContainsString('Сумка', $written);
        self::assertStringContainsString('1.0', $written);
    }

    public function testATemplateWithoutAnObjectRootCannotBeRetyped(): void
    {
        $this->expectException(SnippetRuleException::class);
        $this->expectExceptionMessage('template_not_an_object');

        (new TemplateType())->withType('["Product"]', 'Product');
    }
}
