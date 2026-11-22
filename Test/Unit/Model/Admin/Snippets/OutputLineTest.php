<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Snippets;

use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\OutputLine;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Hryvinskyi\RichSnippets\Model\Overview\OutputFacts;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModulePositions;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * The line and the editor preview's note say where a snippet that is on writes its block as every store view's “Write
 * the Snippets” setting says — the texts of the place in the position pool, a place another module adds included —
 * and name no place when the store views differ
 */
class OutputLineTest extends TestCase
{
    public function testEveryStoreViewWritingIntoTheHeadIsSaidSo(): void
    {
        $line = $this->line([1 => 'head', 2 => 'head']);

        self::assertSame(
            'Each snippet that is on writes one JSON-LD script block into the head of the pages it is set for.',
            $line->line()
        );
    }

    public function testEveryStoreViewWritingAtTheEndOfTheBodyIsSaidSo(): void
    {
        $line = $this->line([1 => 'body_end']);

        self::assertStringContainsString('at the end of the body of the pages it is set for', $line->line());
    }

    public function testStoreViewsThatDifferAreToldByTheirOwnSetting(): void
    {
        $line = $this->line([1 => 'head', 2 => 'body_end']);

        self::assertStringContainsString('where the store view’s “Write the Snippets” setting puts it', $line->line());
    }

    public function testAPlaceAnotherModuleAddsIsSaidInItsOwnWords(): void
    {
        $line = $this->line([1 => 'footer'], ModulePositions::pool(['footer' => [
            'label' => 'In the footer',
            'place' => 'in the footer',
            'line' => 'Each snippet that is on writes one JSON-LD script block into the footer.',
            'note' => 'One script block in the page footer',
        ]]));

        self::assertSame('Each snippet that is on writes one JSON-LD script block into the footer.', $line->line());
        self::assertSame('One script block in the page footer', $line->previewNote());
    }

    public function testThePreviewNoteNamesThePlaceEveryStoreViewWritesAtOrNoneWhenTheyDiffer(): void
    {
        self::assertSame(
            'One script block at the end of the page body',
            $this->line([1 => 'body_end', 2 => 'body_end'])->previewNote()
        );
        self::assertSame(
            'One script block where the store view’s “Write the Snippets” setting puts it',
            $this->line([1 => 'head', 2 => 'body_end'])->previewNote()
        );
    }

    /**
     * The line over store views with the given positions
     *
     * @param array<int, string> $positions the position setting's value by store id
     * @param OutputPositionPool|null $pool the module's places when null
     * @return OutputLine
     */
    private function line(array $positions, ?OutputPositionPool $pool = null): OutputLine
    {
        $settings = $this->createMock(OutputSettings::class);
        $settings->method('position')->willReturnCallback(
            static fn (int $storeId): string => $positions[$storeId] ?? 'head'
        );
        $stores = [];
        foreach (array_keys($positions) as $storeId) {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getId')->willReturn($storeId);
            $stores[] = $store;
        }
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);

        return new OutputLine(
            new OutputFacts($settings, $this->createMock(ThemeMarkupObservationsInterface::class)),
            $storeManager,
            $pool ?? ModulePositions::pool()
        );
    }
}
