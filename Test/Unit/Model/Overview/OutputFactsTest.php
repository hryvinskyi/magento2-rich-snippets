<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\OutputFormat;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupObservationInterface;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupState;
use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Config\OutputSettings;
use Hryvinskyi\RichSnippets\Model\Html\MicrodataItem;
use Hryvinskyi\RichSnippets\Model\Observation\ThemeMarkupObservation;
use Hryvinskyi\RichSnippets\Model\Overview\OutputFacts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How store views write their blocks is told only when they agree, and the theme's own markup is still there in any
 * store view where a page test saw it, or where nothing takes it out and no page test showed the theme writes none
 */
class OutputFactsTest extends TestCase
{
    /**
     * @return array<string,array{bool,bool|null,ThemeMarkupState}>
     */
    public static function themeMarkup(): array
    {
        return [
            'seen, though the settings take it out' => [true, true, ThemeMarkupState::StillThere],
            'taken out and not seen since' => [true, false, ThemeMarkupState::Removed],
            'taken out, no page test yet' => [true, null, ThemeMarkupState::Removed],
            'kept, and a page test saw none' => [false, false, ThemeMarkupState::NoneSeen],
            'kept, and no page test yet' => [false, null, ThemeMarkupState::StillThere],
            'kept, and seen' => [false, true, ThemeMarkupState::StillThere],
        ];
    }

    /**
     * @param bool $removes whether the settings take the markup out
     * @param bool|null $seen what the last page test saw; null when none read a product page
     * @param ThemeMarkupState $expected
     */
    #[DataProvider('themeMarkup')]
    public function testTheThemeMarkupOfAStoreView(bool $removes, ?bool $seen, ThemeMarkupState $expected): void
    {
        $facts = $this->facts([1 => $removes], [1 => $seen]);

        self::assertSame($expected, $facts->themeMarkup([1]));
    }

    public function testOverSeveralStoreViewsTheMarkupIsStillThereWhenItIsInAny(): void
    {
        $facts = $this->facts([1 => true, 2 => false, 3 => false], [1 => null, 2 => false, 3 => true]);

        self::assertSame(ThemeMarkupState::StillThere, $facts->themeMarkup([1, 2, 3]));
        self::assertSame(ThemeMarkupState::Removed, $facts->themeMarkup([1, 2]));
        self::assertSame(ThemeMarkupState::NoneSeen, $facts->themeMarkup([2]));
    }

    public function testTheLayoutIsToldOnlyWhenEveryStoreViewHasTheSame(): void
    {
        $settings = $this->createMock(OutputSettings::class);
        $settings->method('format')->willReturnCallback(
            static fn (int $storeId): OutputFormat => $storeId === 3 ? OutputFormat::Readable : OutputFormat::Compact
        );
        $settings->method('position')->willReturn('body_end');
        $facts = new OutputFacts($settings, $this->createMock(ThemeMarkupObservationsInterface::class));

        self::assertSame(OutputFormat::Compact, $facts->format([1, 2]));
        self::assertNull($facts->format([1, 2, 3]));
        self::assertSame('body_end', $facts->position([1, 2, 3]));
    }

    /**
     * The facts over store views' settings and page tests
     *
     * @param array<int,bool> $removes by store ID
     * @param array<int,bool|null> $seen by store ID; null when no page test read a product page
     * @return OutputFacts
     */
    private function facts(array $removes, array $seen): OutputFacts
    {
        $settings = $this->createMock(OutputSettings::class);
        $settings->method('removesThemeMarkup')->willReturnCallback(
            static fn (int $storeId): bool => $removes[$storeId] ?? false
        );
        $observations = $this->createMock(ThemeMarkupObservationsInterface::class);
        $observations->method('latest')->willReturnCallback(
            static fn (int $storeId): ?ThemeMarkupObservationInterface => ($seen[$storeId] ?? null) === null
                ? null
                : new ThemeMarkupObservation(
                    $storeId,
                    'product',
                    '/bags/field-satchel.html',
                    $seen[$storeId] ? [new MicrodataItem('Product', ['name'])] : [],
                    1795230000
                )
        );

        return new OutputFacts($settings, $observations);
    }
}
