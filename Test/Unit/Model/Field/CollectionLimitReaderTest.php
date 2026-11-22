<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Field;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\RichSnippets\Model\Field\CollectionLimitReader;
use PHPUnit\Framework\TestCase;

/**
 * A list is limited only when every read of it is a limited `@each` without `@if`
 */
class CollectionLimitReaderTest extends TestCase
{
    public function testAListReadOnlyByLimitedEachesTakesTheLargestLimit(): void
    {
        $limits = (new CollectionLimitReader())->limits([
            $this->template($this->object(['review' => $this->each('reviews.latest', 3)])),
            $this->template($this->each('reviews.latest', 5, ['author' => $this->interp('review.nickname')])),
        ]);

        self::assertSame(['reviews.latest' => 5], $limits);
    }

    public function testAListReadAnyOtherWayIsReadWhole(): void
    {
        $reader = new CollectionLimitReader();

        self::assertSame([], $reader->limits([
            $this->template($this->each('reviews.latest', 3)),
            $this->template($this->interp('reviews.latest')),
        ]), 'A list written whole elsewhere.');
        self::assertSame([], $reader->limits([
            $this->template($this->each('reviews.latest', 3)),
            $this->template($this->interp('reviews.latest.0.text')),
        ]), 'An item of the list read by index.');
        self::assertSame([], $reader->limits([
            $this->template($this->each('reviews.latest', null)),
        ]), 'An each without a limit.');
        self::assertSame([], $reader->limits([
            $this->template($this->each('reviews.latest', 3, [], $this->path('review.rating'))),
        ]), 'An each whose items an if can drop.');
    }

    public function testJsonWrittenInATemplateIsNeverReadAsANode(): void
    {
        $literal = ['kind' => 'literal', 'value' => ['kind' => 'path', 'segments' => ['reviews', 'latest']]];

        self::assertSame(['product.gallery' => 1], (new CollectionLimitReader())->limits([
            $this->template($this->object(['a' => $literal, 'b' => $this->each('product.gallery', 1)])),
        ]));
    }

    /**
     * A compiled template with a syntax tree
     *
     * @param array<string, mixed> $ast
     * @return CompiledTemplateInterface
     */
    private function template(array $ast): CompiledTemplateInterface
    {
        $template = $this->createMock(CompiledTemplateInterface::class);
        $template->method('getAst')->willReturn($ast);

        return $template;
    }

    /**
     * An `@each` node
     *
     * @param string $path
     * @param int|null $limit
     * @param array<string, array<string, mixed>> $body
     * @param array<string, mixed>|null $if
     * @return array<string, mixed>
     */
    private function each(string $path, ?int $limit, array $body = [], ?array $if = null): array
    {
        $arguments = ['each' => $this->path($path)];
        if ($limit !== null) {
            $arguments['limit'] = $limit;
        }
        if ($if !== null) {
            $arguments['if'] = $if;
        }

        return ['kind' => 'directive', 'arguments' => $arguments, 'body' => $this->object($body)];
    }

    /**
     * An object node
     *
     * @param array<string, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function object(array $entries): array
    {
        $pairs = [];
        foreach ($entries as $key => $node) {
            $pairs[] = [$key, $node];
        }

        return ['kind' => 'object', 'entries' => $pairs];
    }

    /**
     * An interpolation node
     *
     * @param string $path
     * @return array<string, mixed>
     */
    private function interp(string $path): array
    {
        return ['kind' => 'interp', 'expression' => $this->path($path)];
    }

    /**
     * A path node
     *
     * @param string $path
     * @return array<string, mixed>
     */
    private function path(string $path): array
    {
        return ['kind' => 'path', 'segments' => explode('.', $path)];
    }
}
