<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Snippets;

use Hryvinskyi\RichSnippets\Api\SnippetListInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\AttentionTabCount;
use Hryvinskyi\RichSnippets\Model\Listing\SnippetListing;
use PHPUnit\Framework\TestCase;

/**
 * The Snippets tab counts the snippets of the list of every store view that need attention
 */
class AttentionTabCountTest extends TestCase
{
    public function testTheCountIsTheAttentionCountOfTheListOfEveryStoreView(): void
    {
        $list = $this->createMock(SnippetListInterface::class);
        $list->expects(self::once())
            ->method('list')
            ->with(null)
            ->willReturn(new SnippetListing([], [], [], 4, 7, 2, 1));

        self::assertSame(4, (new AttentionTabCount($list))->count());
    }
}
