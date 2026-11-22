<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Check;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Model\Admin\Check\CheckStatusPresenter;
use Hryvinskyi\RichSnippets\Model\Admin\Check\FindingRows;
use Hryvinskyi\RichSnippets\Model\Check\CheckRun;
use Hryvinskyi\RichSnippets\Model\Check\CheckStatus;
use Hryvinskyi\RichSnippets\Model\Check\Finding;
use Hryvinskyi\RichSnippets\Model\Check\FindingItem;
use Hryvinskyi\RichSnippets\Model\Check\FindingItems;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\BuildsCheckWording;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\EnglishPlurals;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * The "Whole catalog" mode's answers are written exactly as the contract files its browser code is tested against:
 * the status in each of its states, the findings of the design's last night, and the first part of the pages
 * behind the EAN finding
 */
class CheckAnswersContractTest extends TestCase
{
    use BuildsCheckWording;

    private const CONTRACTS = __DIR__ . '/../../../../_contract/admin-pages/';
    private const NOW = 1795269500;
    private const STARTED = 1795230000;
    private const NAMES = ['product' => 'Product', 'product-variants' => 'Product with variants'];
    private const LABELS = [
        'product.ean' => 'EAN',
        'product.short_description' => 'Short description',
        'product.image' => 'Main image',
    ];
    private const FACTS = ['product' => 'Products', 'category' => 'Categories', 'cms' => 'CMS pages'];

    public function testEveryStateOfTheCheckIsWrittenAsTheContractWritesIt(): void
    {
        self::assertSame($this->contract('test-catalog-status.json'), self::travelled($this->states()));
    }

    public function testTheFindingsAreWrittenAsTheContractWritesThem(): void
    {
        $rows = new FindingRows($this->findingWording(self::LABELS), new WordList(), new EnglishPlurals());

        self::assertSame(
            $this->contract('test-catalog-findings.json'),
            self::travelled([
                'findings' => $rows->table($this->findings(), null, self::NAMES),
                'clean' => $rows->table([$this->findings()[3]], 1, self::NAMES),
                'none' => $rows->table([], null, self::NAMES),
            ])
        );
    }

    public function testThePagesBehindAFindingAreWrittenAsTheContractWritesThem(): void
    {
        $rows = new FindingRows($this->findingWording(self::LABELS), new WordList(), new EnglishPlurals());
        $ean = $this->findings()[1];
        $items = [
            new FindingItem('product', 31, 1, 'Leather care kit', 'LC-CARE-01', 'Accessories'),
            new FindingItem('product', 32, 1, 'Key fob', 'LC-KF-TAN', 'Small leather goods'),
            new FindingItem('product', 33, 2, 'Shoulder strap, wide', 'LC-STR-W', 'Accessories'),
        ];

        self::assertSame(
            $this->contract('test-catalog-finding-items.json'),
            self::travelled([
                'first' => $rows->items(new FindingItems($ean, 23, $items), 1, 8, self::NAMES),
                'later' => $rows->items(new FindingItems($ean, 23, $items), 2, 8, self::NAMES),
                'all' => $rows->items(new FindingItems($ean, 3, $items), 1, 8, self::NAMES),
            ])
        );
    }

    public function testTheStatusSaysPagesWhenNotEveryInvalidPageIsAProduct(): void
    {
        $answer = $this->presenter()->present(new CheckStatus(null, $this->last(), null, [], [
            'product' => 1,
            'category' => 1,
        ]));

        self::assertSame('2 pages are invalid', $answer['title']);
        self::assertSame('invalid', $answer['tone']);
    }

    /**
     * The status in each state
     *
     * @return array<string, mixed>
     */
    private function states(): array
    {
        $presenter = $this->presenter();
        $last = $this->last();
        $pages = ['home' => 1, 'product' => 184, 'category' => 14, 'cms' => 9];

        return [
            'idle' => $presenter->present(
                new CheckStatus(null, $last, null, $pages, ['product' => 2], ['product' => 26])
            ),
            'running' => $presenter->present(new CheckStatus(
                new CheckRun(
                    8,
                    CheckState::Running,
                    CheckTrigger::Manual,
                    self::NOW - 40,
                    self::NOW - 20,
                    null,
                    self::NOW - 2,
                    207,
                    112,
                    3
                ),
                $last,
                null,
                $pages,
                ['product' => 2],
                ['product' => 26]
            )),
            'waiting' => $presenter->present(new CheckStatus(
                new CheckRun(8, CheckState::Requested, CheckTrigger::Manual, self::NOW - 40),
                $last,
                null,
                $pages,
                ['product' => 2],
                ['product' => 26]
            )),
            'clean' => $presenter->present(new CheckStatus(null, $last, null, $pages)),
            'failed' => $presenter->present(new CheckStatus(
                null,
                $last,
                new CheckRun(
                    8,
                    CheckState::Failed,
                    CheckTrigger::Import,
                    self::NOW - 4000,
                    self::NOW - 3990,
                    self::NOW - 1000,
                    self::NOW - 2000,
                    207,
                    40,
                    3,
                    'stale'
                ),
                $pages,
                ['product' => 2],
                ['product' => 26]
            )),
            'none' => $presenter->present(new CheckStatus()),
        ];
    }

    /**
     * Last night's run: 03:00, 41 seconds, three store views
     *
     * @return CheckRunInterface
     */
    private function last(): CheckRunInterface
    {
        return new CheckRun(
            7,
            CheckState::Done,
            CheckTrigger::Schedule,
            self::STARTED - 10,
            self::STARTED,
            self::STARTED + 41,
            self::STARTED + 41,
            207,
            207,
            3
        );
    }

    /**
     * The status presenter at the test's time
     *
     * @return CheckStatusPresenter
     */
    private function presenter(): CheckStatusPresenter
    {
        $clock = new FixedClock(self::NOW);

        return new CheckStatusPresenter(
            $this->runTimes($clock),
            $clock,
            new EnglishPlurals(),
            new CountWords(new EnglishPlurals()),
            self::FACTS
        );
    }

    /**
     * The design's findings, worst first
     *
     * @return list<Finding>
     */
    private function findings(): array
    {
        $both = ['product', 'product-variants'];

        return [
            $this->finding('merchant-listing-image', PropertyTone::Bad, 'image', ['product.image'], ['product'], 2),
            $this->finding(
                'merchant-listing-gtin13-product-ean',
                PropertyTone::Warn,
                'gtin13',
                ['product.ean'],
                $both,
                23
            ),
            $this->finding(
                'merchant-listing-description-product-short-description',
                PropertyTone::Warn,
                'description',
                ['product.short_description'],
                ['product'],
                5
            ),
            new Finding(
                'review-stars-left-out-reviews-rating-reviews-count',
                PropertyTone::Note,
                'review_stars',
                '',
                ['reviews.rating', 'reviews.count'],
                $both,
                71,
                ['product' => 71]
            ),
        ];
    }

    /**
     * A finding of a merchant listing on product pages
     *
     * @param string $key
     * @param PropertyTone $tone
     * @param string $path
     * @param list<string> $sources
     * @param list<string> $snippets
     * @param int $pages
     * @return Finding
     */
    private function finding(
        string $key,
        PropertyTone $tone,
        string $path,
        array $sources,
        array $snippets,
        int $pages
    ): Finding {
        return new Finding($key, $tone, 'merchant_listing', $path, $sources, $snippets, $pages, ['product' => $pages]);
    }

    /**
     * An answer as it reaches the browser
     *
     * @param array<mixed> $answer
     * @return array<mixed>
     */
    private static function travelled(array $answer): array
    {
        $decoded = json_decode(
            (string) json_encode($answer, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * A contract file, decoded
     *
     * @param string $file
     * @return array<mixed>
     */
    private function contract(string $file): array
    {
        $contract = json_decode((string) file_get_contents(self::CONTRACTS . $file), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);

        return $contract;
    }
}
