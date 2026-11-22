<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Api\Data\CheckStatusInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckStatusInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\FindingItemsInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\FindingsCsvInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Model\Check\CatalogCheck;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultReaderInterface;
use Hryvinskyi\RichSnippets\Model\Check\CheckStatus;
use Hryvinskyi\RichSnippets\Model\Check\Finding;
use Hryvinskyi\RichSnippets\Model\Check\FindingItemLabels;
use Hryvinskyi\RichSnippets\Model\Check\FindingsCsvContent;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\FixedClock;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\InMemoryCheckRuns;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;

/**
 * The catalog check asks once while a run is pending, reads the last finished run's findings worst first, tells a
 * failed run after it apart, and refuses a finding it did not find
 */
class CatalogCheckTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testAskingTwiceWhileARunIsPendingAsksOnce(): void
    {
        $runs = new InMemoryCheckRuns();
        $check = $this->catalogCheck($runs, []);

        $first = $check->request(CheckTrigger::Manual);
        $second = $check->request(CheckTrigger::Import);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame(CheckTrigger::Manual, $second->getTrigger());
        self::assertCount(1, $runs->rows);
    }

    public function testTheFindingsComeWorstFirstThenByPages(): void
    {
        $runs = new InMemoryCheckRuns();
        $runs->add(CheckState::Done);
        $check = $this->catalogCheck($runs, [
            $this->finding('rev', PropertyTone::Note, 71),
            $this->finding('desc', PropertyTone::Warn, 5),
            $this->finding('img', PropertyTone::Bad, 2),
            $this->finding('ean', PropertyTone::Warn, 23),
        ]);

        self::assertSame(
            ['img', 'ean', 'desc', 'rev'],
            array_map(static fn (FindingInterface $finding): string => $finding->getKey(), $check->findings(null))
        );
    }

    public function testBeforeARunHasFinishedThereAreNoFindings(): void
    {
        $runs = new InMemoryCheckRuns();
        $runs->add(CheckState::Running);

        self::assertSame([], $this->catalogCheck($runs, [$this->finding('img', PropertyTone::Bad, 2)])->findings(1));
    }

    public function testAFailedRunNewerThanTheLastFinishedOneIsTold(): void
    {
        $runs = new InMemoryCheckRuns();
        $runs->add(CheckState::Done);
        $failed = $runs->add(CheckState::Failed);

        $status = $this->catalogCheck($runs, [])->status();

        self::assertSame($failed, $status->getFailed()?->getId());
        self::assertSame(1, $status->getLast()?->getId());
        self::assertNull($status->getCurrent());
    }

    public function testAFindingTheLastRunDidNotFindIsRefused(): void
    {
        $runs = new InMemoryCheckRuns();
        $runs->add(CheckState::Done);

        $this->expectException(NoSuchEntityException::class);
        $this->catalogCheck($runs, [])->findingItems('img', null, 1);
    }

    public function testAListStartsAtItsFirstPart(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->catalogCheck(new InMemoryCheckRuns(), [])->findingItems('img', null, 0);
    }

    /**
     * The catalog check over fakes, its last run having found the given findings
     *
     * @param InMemoryCheckRuns $runs
     * @param list<FindingInterface> $findings
     * @return CatalogCheck
     */
    private function catalogCheck(InMemoryCheckRuns $runs, array $findings): CatalogCheck
    {
        $results = $this->createMock(CheckResultReaderInterface::class);
        $results->method('findings')->willReturn($findings);
        $results->method('finding')->willReturn(null);
        $results->method('aggregates')->willReturn([]);
        $statuses = $this->createMock(CheckStatusInterfaceFactory::class);
        $statuses->method('create')->willReturnCallback(
            static fn (array $data): CheckStatusInterface => new CheckStatus(
                ($data['current'] ?? null) instanceof CheckRunInterface ? $data['current'] : null,
                ($data['last'] ?? null) instanceof CheckRunInterface ? $data['last'] : null,
                ($data['failed'] ?? null) instanceof CheckRunInterface ? $data['failed'] : null
            )
        );

        return new CatalogCheck(
            $runs,
            $results,
            $this->createMock(FindingItemLabels::class),
            new FindingsCsvContent(),
            $statuses,
            $this->createMock(FindingItemsInterfaceFactory::class),
            $this->createMock(FindingsCsvInterfaceFactory::class),
            new FixedClock(self::NOW)
        );
    }

    /**
     * A finding of product pages
     *
     * @param string $key
     * @param PropertyTone $tone
     * @param int $pages
     * @return FindingInterface
     */
    private function finding(string $key, PropertyTone $tone, int $pages): FindingInterface
    {
        return new Finding($key, $tone, 'merchant_listing', $key, [], ['product'], $pages, ['product' => $pages]);
    }
}
