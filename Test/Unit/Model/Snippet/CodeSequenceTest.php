<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\CodeSequence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Codes made from slugs stay in the code grammar, and a taken code moves to the next free one of its sequence
 */
class CodeSequenceTest extends TestCase
{
    #[DataProvider('slugs')]
    public function testTheBaseOfASlugIsACode(string $slug, string $expected): void
    {
        $base = (new CodeSequence())->baseOf($slug);

        self::assertSame($expected, $base);
        self::assertMatchesRegularExpression(SnippetInterface::CODE_PATTERN, $base);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function slugs(): array
    {
        return [
            'plain' => ['product', 'product'],
            'words' => ['new-snippet', 'new-snippet'],
            'hyphens at the ends and doubled' => ['--return--policy--', 'return-policy'],
            'leading digit' => ['404-page', 'snippet-404-page'],
            'one letter' => ['a', 'snippet-a'],
            'nothing left' => ['', 'snippet'],
            'other characters dropped' => ['faq_page!', 'faqpage'],
            'too long' => [str_repeat('ab-', 30), rtrim(substr(str_repeat('ab-', 30), 0, 64), '-')],
        ];
    }

    public function testAFreeBaseIsTheCode(): void
    {
        self::assertSame('product', (new CodeSequence())->firstFree('product', ['product-2', 'products']));
    }

    public function testATakenBaseMovesToTheFirstFreeNumber(): void
    {
        self::assertSame(
            'product-4',
            (new CodeSequence())->firstFree('product', ['product', 'product-2', 'product-3', 'product-5'])
        );
    }

    public function testANumberedCodeIsCutToStayWithinSixtyFourCharacters(): void
    {
        $base = 'a' . str_repeat('b', 63);

        $code = (new CodeSequence())->firstFree($base, [$base]);

        self::assertSame(substr($base, 0, 62) . '-2', $code);
        self::assertMatchesRegularExpression(SnippetInterface::CODE_PATTERN, $code);
    }

    public function testEveryCodeOfTheSequenceStartsWithThePrefix(): void
    {
        $sequence = new CodeSequence();
        $base = 'a' . str_repeat('b', 63);
        $prefix = $sequence->prefixOf($base);

        self::assertTrue(str_starts_with($sequence->firstFree($base, [$base]), $prefix));
        self::assertTrue(str_starts_with($base, $prefix));
        self::assertSame(52, strlen($prefix));
    }
}
