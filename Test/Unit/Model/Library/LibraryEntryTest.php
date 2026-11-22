<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Model\Library\LibraryEntry;
use Hryvinskyi\RichSnippets\Model\Library\LibraryTemplate;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use PHPUnit\Framework\TestCase;

/**
 * A library entry says whether the shop has a copy, and whether a copy came from an older version of the template
 */
class LibraryEntryTest extends TestCase
{
    public function testACopyFromAnOlderVersionMeansTheLibraryHasANewerOne(): void
    {
        self::assertTrue($this->entry(3, [2, 3])->hasNewerVersion());
        self::assertTrue($this->entry(3, [2, 3])->isAdded());
    }

    public function testCopiesOfTheCurrentVersionHaveNothingNewer(): void
    {
        self::assertFalse($this->entry(3, [3])->hasNewerVersion());
    }

    public function testATemplateWithoutCopiesIsNotAddedAndHasNothingNewer(): void
    {
        $entry = $this->entry(3, []);

        self::assertFalse($entry->isAdded());
        self::assertFalse($entry->hasNewerVersion());
    }

    public function testABuildingBlockCannotBeGivenAPlacement(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LibraryTemplate(
            'return-policy',
            1,
            'blocks',
            'Return policy',
            'MerchantReturnPolicy',
            'icon',
            'Text',
            'Inside',
            true,
            GoogleStatus::Ok,
            'Short',
            'Long',
            [],
            [],
            [],
            [],
            ['product'],
            PlacementFixture::none(),
            ['@type' => 'X']
        );
    }

    /**
     * An entry of a template version with copies of some versions
     *
     * @param int $version
     * @param list<int> $added
     * @return LibraryEntry
     */
    private function entry(int $version, array $added): LibraryEntry
    {
        $template = new LibraryTemplate(
            'product',
            $version,
            'product',
            'Product',
            'Product',
            'icon',
            'Text',
            'Product pages',
            false,
            GoogleStatus::Ok,
            'Short',
            'Long',
            [],
            [],
            [],
            [],
            ['product'],
            PlacementFixture::none(),
            ['@type' => 'Product']
        );

        return new LibraryEntry($template, $added, [], 'Product');
    }
}
