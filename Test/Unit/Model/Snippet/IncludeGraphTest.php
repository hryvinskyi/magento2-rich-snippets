<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Snippet;

use Hryvinskyi\RichSnippets\Model\Snippet\IncludeGraph;
use PHPUnit\Framework\TestCase;

/**
 * Loops a template would close, and the snippets a change reaches through includes
 */
class IncludeGraphTest extends TestCase
{
    public function testASnippetIncludingItselfIsALoop(): void
    {
        self::assertSame(['faq', 'faq'], (new IncludeGraph())->loopThrough('faq', ['faq'], []));
    }

    public function testALoopThroughStoredSnippetsIsFoundWithEverySnippetOnIt(): void
    {
        $edges = [
            'return-policy' => ['postal-address'],
            'postal-address' => ['organization'],
            'organization' => [],
        ];

        self::assertSame(
            ['organization', 'return-policy', 'postal-address', 'organization'],
            (new IncludeGraph())->loopThrough('organization', ['return-policy'], $edges)
        );
    }

    public function testTheShortestLoopIsReported(): void
    {
        $edges = ['a' => ['b', 'c'], 'b' => ['d'], 'c' => ['a'], 'd' => ['a']];

        self::assertSame(['a', 'c', 'a'], (new IncludeGraph())->loopThrough('a', ['b', 'c'], $edges));
    }

    public function testTheSavedIncludesReplaceTheStoredOnes(): void
    {
        $edges = ['product' => ['return-policy'], 'return-policy' => ['product']];

        self::assertSame([], (new IncludeGraph())->loopThrough('return-policy', [], $edges));
    }

    public function testSharedIncludesWithoutALoopAreNoLoop(): void
    {
        $edges = [
            'product' => ['return-policy', 'organization'],
            'organization' => ['postal-address'],
            'return-policy' => ['postal-address'],
            'postal-address' => [],
        ];

        self::assertSame([], (new IncludeGraph())->loopThrough('product', ['return-policy', 'organization'], $edges));
    }

    public function testAnUnknownCodeIncludesNothing(): void
    {
        self::assertSame([], (new IncludeGraph())->loopThrough('product', ['missing'], ['product' => []]));
    }

    public function testIncludersAreFoundThroughEveryLevelNearestFirst(): void
    {
        $edges = [
            'product' => ['offer'],
            'product-group' => ['product'],
            'offer' => ['return-policy'],
            'organization' => ['return-policy'],
            'return-policy' => [],
            'faq' => [],
        ];

        self::assertSame(
            ['offer', 'organization', 'product', 'product-group'],
            (new IncludeGraph())->includersOf('return-policy', $edges)
        );
    }

    public function testIncludersOfALoopEndWithoutRepeating(): void
    {
        $edges = ['a' => ['b'], 'b' => ['a']];

        self::assertSame(['b'], (new IncludeGraph())->includersOf('a', $edges));
    }

    public function testDirectIncludersAreOnlyThoseNamingTheCodeAndNeverTheSnippetItself(): void
    {
        $edges = ['faq' => ['faq'], 'product' => ['offer'], 'offer' => ['faq'], 'zebra' => ['faq']];

        self::assertSame(['offer', 'zebra'], (new IncludeGraph())->directIncludersOf('faq', $edges));
    }
}
