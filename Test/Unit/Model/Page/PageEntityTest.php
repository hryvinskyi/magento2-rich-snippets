<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Page;

use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A page description is valid or never exists
 */
class PageEntityTest extends TestCase
{
    public function testAPageKeepsItsKindEntityAndStoreView(): void
    {
        $page = new PageEntity('product', 12, 1);

        self::assertSame('product', $page->getKind());
        self::assertSame(12, $page->getEntityId());
        self::assertSame(1, $page->getStoreId());
        self::assertNull((new PageEntity('other', null, 0))->getEntityId());
    }

    /**
     * @return array<string, array{string, int|null, int}>
     */
    public static function invalidPages(): array
    {
        return [
            'no kind' => ['', 1, 1],
            'an entity ID of zero' => ['product', 0, 1],
            'a negative entity ID' => ['product', -4, 1],
            'a negative store ID' => ['product', 4, -1],
        ];
    }

    #[DataProvider('invalidPages')]
    public function testAnInvalidPageIsRefused(string $kind, ?int $entityId, int $storeId): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PageEntity($kind, $entityId, $storeId);
    }
}
