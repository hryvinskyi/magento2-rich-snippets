<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Requirement;

use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;
use Hryvinskyi\RichSnippets\Model\Requirement\TraceReading;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\RecordingTraceFake;
use PHPUnit\Framework\TestCase;

/**
 * Trace entries become dot paths without list indexes, merged across list items; values and the root are dropped;
 * quoted keys are read back; sources come from the property, the properties inside it, or the one around it
 */
class TraceReadingTest extends TestCase
{
    public function testEntriesBecomeDotPathsMergedAcrossListItems(): void
    {
        $trace = new RecordingTraceFake();
        $trace->record('$.review[0].author', TraceOutcome::Empty, ['review.nickname']);
        $trace->record('$.review[1].author', TraceOutcome::Empty, ['review.nickname', 'review.title']);
        $trace->record("\$['odd key'].x", TraceOutcome::Skipped, ['store.name']);
        $trace->record("\$['it\\'s']", TraceOutcome::Empty, []);
        $trace->record('$.name', TraceOutcome::Value, []);
        $trace->record('$', TraceOutcome::Empty, []);
        $trace->record('not a path', TraceOutcome::Empty, ['x']);

        self::assertSame(
            [
                'review.author' => ['outcome' => TraceOutcome::Empty, 'sources' => ['review.nickname', 'review.title']],
                'odd key.x' => ['outcome' => TraceOutcome::Skipped, 'sources' => ['store.name']],
                "it's" => ['outcome' => TraceOutcome::Empty, 'sources' => []],
            ],
            (new TraceReading())->leftOut($trace)
        );
    }

    public function testSourcesComeFromThePropertyThenInsideItThenAroundIt(): void
    {
        $trace = new RecordingTraceFake();
        $trace->record('$.offers.price', TraceOutcome::Empty, ['product.final_price']);
        $trace->record('$.offers', TraceOutcome::Skipped, ['product.on_sale']);
        $trace->record('$.brand.name', TraceOutcome::Empty, ['product.manufacturer']);
        $trace->record('$.brand', TraceOutcome::Empty, []);
        $reading = new TraceReading();
        $entries = $reading->leftOut($trace);

        self::assertSame(['product.final_price'], $reading->sourcesFor($entries, 'offers.price'));
        self::assertSame(['product.manufacturer'], $reading->sourcesFor($entries, 'brand'));
        self::assertSame(['product.on_sale'], $reading->sourcesFor($entries, 'offers.priceCurrency'));
        self::assertSame([], $reading->sourcesFor($entries, 'image'));
        self::assertSame(['offers', 'brand'], array_keys($reading->outermost($entries)));
    }
}
