<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Data;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Data\EntityDataSource;
use PHPUnit\Framework\TestCase;

/**
 * A part is loaded once for every named entity of a store view, on the first read of any of them
 */
class EntityDataSourceTest extends TestCase
{
    /**
     * @var list<array{ids: list<int>, store: int, paths: list<string>, limits: array<int|string, int>}>
     */
    private array $calls = [];

    public function testAPartIsLoadedOnceForEveryNamedEntity(): void
    {
        $source = new EntityDataSource(['price' => $this->part()]);
        $source->prefetch([1, 2, 3], 1, ['product.final_price'], ['reviews.latest' => 3]);

        self::assertSame('price of 2', $source->get(2, 1, 'price'));
        self::assertSame('price of 1', $source->get(1, 1, 'price'));
        self::assertNull($source->get(3, 1, 'price'), 'An entity the part holds nothing for reads as null.');

        self::assertCount(1, $this->calls);
        self::assertSame([2, 1, 3], $this->calls[0]['ids']);
        self::assertSame(['product.final_price'], $this->calls[0]['paths']);
        self::assertSame(['reviews.latest' => 3], $this->calls[0]['limits']);
    }

    public function testAnEntityReadWithoutBeingNamedIsLoadedOnItsOwn(): void
    {
        $source = new EntityDataSource(['price' => $this->part()]);
        $source->prefetch([1], 1, []);

        self::assertSame('price of 1', $source->get(1, 1, 'price'));
        self::assertSame('price of 9', $source->get(9, 1, 'price'));
        self::assertSame([[1], [9]], array_column($this->calls, 'ids'));
    }

    public function testStoreViewsAreLoadedApartAndResetForgetsEverything(): void
    {
        $source = new EntityDataSource(['price' => $this->part()]);
        $source->prefetch([1, 2], 1, []);
        $source->prefetch([1], 2, []);

        $source->get(1, 1, 'price');
        $source->get(1, 2, 'price');
        $source->reset();
        $source->get(2, 1, 'price');

        self::assertSame([[1, 2], [1], [2]], array_column($this->calls, 'ids'));
        self::assertSame([1, 2, 1], array_column($this->calls, 'store'));
    }

    public function testAnUnknownPartFailsLoudly(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        (new EntityDataSource(['price' => $this->part()]))->get(1, 1, 'gallery');
    }

    /**
     * A part that records its calls and holds a value for entities 1, 2 and 9
     *
     * @return EntityDataPartInterface
     */
    private function part(): EntityDataPartInterface
    {
        $part = $this->createMock(EntityDataPartInterface::class);
        $part->method('load')->willReturnCallback(
            function (array $ids, int $storeId, array $paths, array $limits): array {
                $ids = array_values(array_filter($ids, 'is_int'));
                $this->calls[] = [
                    'ids' => $ids,
                    'store' => $storeId,
                    'paths' => array_values(array_filter($paths, 'is_string')),
                    'limits' => array_filter($limits, 'is_int'),
                ];
                $values = [];
                foreach ($ids as $id) {
                    if ($id !== 3) {
                        $values[$id] = 'price of ' . $id;
                    }
                }

                return $values;
            }
        );

        return $part;
    }
}
