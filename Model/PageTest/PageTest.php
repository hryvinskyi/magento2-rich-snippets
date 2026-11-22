<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\PageTest;

use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageTestResultInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageTestResultInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\StorefrontPageInterface;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupObservationInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Exception\StorefrontReadException;
use Hryvinskyi\RichSnippets\Api\HtmlStructuredDataScannerInterface;
use Hryvinskyi\RichSnippets\Api\PageTestInterface;
use Hryvinskyi\RichSnippets\Api\StorefrontReaderInterface;
use Hryvinskyi\RichSnippets\Api\ThemeMarkupObservationsInterface;
use Hryvinskyi\RichSnippets\Model\Clock\ClockInterface;
use Hryvinskyi\RichSnippets\Model\Clock\MonotonicClockInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\ThemeMarkupTypes;

/**
 * Tests a page: reads it from the storefront, finds its structured data, checks its blocks, keeps what it saw of the
 * theme's microdata and works out why the other snippets are not there
 *
 * The duration covers the whole test. The result and the observation kept for the store view and kind of page list
 * the same items: those the theme describes with microdata of the types a snippet replaces, so an item that stays on
 * the page once the theme's product markup is removed is not taken for it. A page that cannot be read gives a failed
 * result and keeps no observation.
 *
 * @phpstan-import-type Tested from TestedPages
 */
class PageTest implements PageTestInterface
{
    /**
     * @param TestedPages $pages
     * @param StorefrontReaderInterface $reader
     * @param HtmlStructuredDataScannerInterface $scanner
     * @param TestedBlocks $blocks
     * @param MissingSnippets $missing
     * @param ThemeMarkupObservationsInterface $observations
     * @param ThemeMarkupObservationInterfaceFactory $observationFactory
     * @param ThemeMarkupTypes $themeMarkupTypes
     * @param PageTestResultInterfaceFactory $results
     * @param ClockInterface $clock
     * @param MonotonicClockInterface $stopwatch
     */
    public function __construct(
        private readonly TestedPages $pages,
        private readonly StorefrontReaderInterface $reader,
        private readonly HtmlStructuredDataScannerInterface $scanner,
        private readonly TestedBlocks $blocks,
        private readonly MissingSnippets $missing,
        private readonly ThemeMarkupObservationsInterface $observations,
        private readonly ThemeMarkupObservationInterfaceFactory $observationFactory,
        private readonly ThemeMarkupTypes $themeMarkupTypes,
        private readonly PageTestResultInterfaceFactory $results,
        private readonly ClockInterface $clock,
        private readonly MonotonicClockInterface $stopwatch
    ) {
    }

    /**
     * @inheritDoc
     */
    public function testPage(PageEntityInterface $page): PageTestResultInterface
    {
        $started = $this->stopwatch->seconds();

        return $this->test($this->pages->ofPage($page), $started);
    }

    /**
     * @inheritDoc
     */
    public function testPath(string $path, int $storeId): PageTestResultInterface
    {
        $started = $this->stopwatch->seconds();

        return $this->test($this->pages->ofPath($path, $storeId), $started);
    }

    /**
     * Read and examine the page
     *
     * @param Tested $tested
     * @param float $started on the stopwatch
     * @return PageTestResultInterface
     */
    private function test(array $tested, float $started): PageTestResultInterface
    {
        $page = $tested['page'];
        $testedAt = $this->clock->now();
        try {
            $read = $this->reader->read($page->getStoreId(), $tested['path']);
        } catch (StorefrontReadException $failure) {
            return $this->results->create($this->common($tested, $testedAt, $started) + [
                'readPath' => null,
                'failure' => $failure->getReason(),
                'httpStatus' => $failure->getHttpStatus(),
            ]);
        }

        return $this->examined($tested, $read, $testedAt, $started);
    }

    /**
     * The result of a page that was read
     *
     * @param Tested $tested
     * @param StorefrontPageInterface $read
     * @param int $testedAt
     * @param float $started
     * @return PageTestResultInterface
     */
    private function examined(
        array $tested,
        StorefrontPageInterface $read,
        int $testedAt,
        float $started
    ): PageTestResultInterface {
        $page = $tested['page'];
        $found = $this->scanner->scan($read->getBody());
        $blocks = $this->blocks->of($found->getBlocks());
        $carried = [];
        foreach ($found->getBlocks() as $block) {
            if ($block->getCode() !== null) {
                $carried[] = $block->getCode();
            }
        }
        $themeMarkup = $this->themeMarkupTypes->itemsAmong($found->getMicrodata());
        $this->observations->record($this->observationFactory->create([
            'storeId' => $page->getStoreId(),
            'kind' => $page->getKind(),
            'path' => $read->getPath(),
            'items' => $themeMarkup,
            'seenAt' => $testedAt,
        ]));
        $missing = $this->missing->of($page, $carried);

        return $this->results->create($this->common($tested, $testedAt, $started) + [
            'readPath' => $read->getPath(),
            'blocks' => $blocks,
            'themeMarkup' => $themeMarkup,
            'exclusions' => $missing['exclusions'],
            'unwritten' => $missing['unwritten'],
        ]);
    }

    /**
     * What every result has: the page, its name and path, when the test ran and how long it took
     *
     * @param Tested $tested
     * @param int $testedAt
     * @param float $started
     * @return array<string, mixed>
     */
    private function common(array $tested, int $testedAt, float $started): array
    {
        return [
            'page' => $tested['page'],
            'label' => $tested['label'],
            'path' => $tested['path'],
            'testedAt' => $testedAt,
            'durationMs' => max(0, (int) round(($this->stopwatch->seconds() - $started) * 1000)),
        ];
    }
}
