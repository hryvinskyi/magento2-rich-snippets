<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Flag;

use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetFlagFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultReaderInterface;
use Hryvinskyi\RichSnippets\Model\Check\Finding;
use Hryvinskyi\RichSnippets\Model\Flag\Provider\FindingFlags;
use Hryvinskyi\RichSnippets\Model\Flag\Provider\SameTypeOverlapFlags;
use Hryvinskyi\RichSnippets\Model\Flag\SnippetFlag;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\BuildsCheckWording;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\InMemoryCheckRuns;
use PHPUnit\Framework\TestCase;

/**
 * The snippet list's flags from the last catalog check: a warning on the second snippet of a type on the same
 * pages, and a note per gap worth fixing on each snippet whose blocks show it
 */
class CheckFlagsTest extends TestCase
{
    use BuildsCheckWording;

    public function testTheSecondSnippetOfATypeIsWarnedTheFirstIsNot(): void
    {
        $runs = new InMemoryCheckRuns();
        $runs->add(CheckState::Done);
        $results = $this->createMock(CheckResultReaderInterface::class);
        $results->expects(self::once())->method('aggregates')->with(1, 0)
            ->willReturn(['second_of_type' => ['product-imported' => 19]]);

        $flags = (new SameTypeOverlapFlags($runs, $results, $this->flagFactory()))->flags(
            [$this->snippet('product', 'Product'), $this->snippet('product-imported', 'Product')],
            null
        );

        self::assertSame(['product-imported' => [['warn', 'Second Product on the same pages']]], $this->read($flags));
    }

    public function testBeforeACheckHasFinishedNoSnippetIsWarned(): void
    {
        $results = $this->createMock(CheckResultReaderInterface::class);
        $results->expects(self::never())->method('aggregates');

        self::assertSame(
            [],
            (new SameTypeOverlapFlags(new InMemoryCheckRuns(), $results, $this->flagFactory()))
                ->flags([$this->snippet('product', 'Product')], 1)
        );
    }

    public function testEachGapWorthFixingIsNotedOnTheSnippetsThatShowIt(): void
    {
        $catalogCheck = $this->createMock(CatalogCheckInterface::class);
        $catalogCheck->method('findings')->with(1)->willReturn([
            new Finding('img', PropertyTone::Bad, 'merchant_listing', 'image', [], ['product'], 2, ['product' => 2]),
            new Finding('ean', PropertyTone::Warn, 'merchant_listing', 'gtin13', ['product.ean'], [
                'product',
                'variants',
                'gone',
            ], 23, ['product' => 23]),
            new Finding('rev', PropertyTone::Note, 'review_stars', '', [], ['product'], 71, ['product' => 71]),
        ]);
        $wording = $this->findingWording(['product.ean' => 'EAN']);
        $provider = new FindingFlags($catalogCheck, $wording, $this->flagFactory());

        $flags = $provider->flags([$this->snippet('product', 'Product'), $this->snippet('variants', 'Product')], 1);

        self::assertSame(
            [
                'product' => [['note', 'No image for 2 products'], ['note', 'EAN is empty for 23 products']],
                'variants' => [['note', 'EAN is empty for 23 products']],
            ],
            $this->read($flags)
        );
    }

    /**
     * The flags as tone and text
     *
     * @param array<string, list<SnippetFlagInterface>> $flags
     * @return array<string, list<array{string, string}>>
     */
    private function read(array $flags): array
    {
        return array_map(
            static fn (array $list): array => array_map(
                static fn (SnippetFlagInterface $flag): array => [$flag->getTone()->value, $flag->getText()],
                $list
            ),
            $flags
        );
    }

    /**
     * A flag factory making plain flags
     *
     * @return SnippetFlagFactoryInterface
     */
    private function flagFactory(): SnippetFlagFactoryInterface
    {
        $factory = $this->createMock(SnippetFlagFactoryInterface::class);
        $factory->method('create')->willReturnCallback(
            static fn (FlagTone $tone, string $text): SnippetFlagInterface => new SnippetFlag($tone, $text)
        );

        return $factory;
    }

    /**
     * A snippet with a code and a type
     *
     * @param string $code
     * @param string $type
     * @return SnippetInterface
     */
    private function snippet(string $code, string $type): SnippetInterface
    {
        $snippet = $this->createMock(SnippetInterface::class);
        $snippet->method('getCode')->willReturn($code);
        $snippet->method('getType')->willReturn($type);

        return $snippet;
    }
}
