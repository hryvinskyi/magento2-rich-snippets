<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test;

use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\TestedBlockInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Summary\BlockSummaries;

/**
 * Writes the JSON-LD blocks of a tested page as the "What this page outputs" card lists them
 *
 * A block has a problem (`bad`) when its JSON cannot be read, or when an object misses a property Google requires
 * for a result it may not leave out; its summary then says what is missing. Otherwise (`ok`) the summary is the one
 * its first object's type has. The JSON is shown indented when the page carries it on one line; the size is the
 * size the page carries, in bytes; the lines are those of the JSON shown. A block no snippet wrote has no name.
 *
 * @phpstan-type Block array{code: string|null, snippetId: int|null, name: string|null, type: string, json: string,
 *     size: int, lines: int, verdict: string, summary: string}
 */
class BlockRows
{
    private const OK = 'ok';
    private const BAD = 'bad';
    private const SHOWN = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @param BlockSummaries $summaries
     * @param WordList $words
     */
    public function __construct(
        private readonly BlockSummaries $summaries,
        private readonly WordList $words
    ) {
    }

    /**
     * The rows of a page's blocks
     *
     * @param list<TestedBlockInterface> $blocks
     * @return list<Block>
     */
    public function rows(array $blocks): array
    {
        return array_map(fn (TestedBlockInterface $block): array => $this->row($block), $blocks);
    }

    /**
     * One row
     *
     * @param TestedBlockInterface $tested
     * @return Block
     */
    private function row(TestedBlockInterface $tested): array
    {
        $block = $tested->getBlock();
        $snippet = $tested->getSnippet();
        $json = $this->shown($block->getJson(), $block->getValue());
        $failing = $this->failing($tested);
        $readable = $block->getValue() !== null;

        return [
            'code' => $block->getCode(),
            'snippetId' => $snippet?->getId(),
            'name' => $snippet?->getName(),
            'type' => $tested->getType(),
            'json' => $json,
            'size' => strlen($block->getJson()),
            'lines' => substr_count($json, "\n") + 1,
            'verdict' => $readable && $failing === null ? self::OK : self::BAD,
            'summary' => $this->summary($tested, $readable, $failing),
        ];
    }

    /**
     * The first result a block's objects miss a required property of and may not leave out; null when none
     *
     * @param TestedBlockInterface $tested
     * @return FeatureCheckInterface|null
     */
    private function failing(TestedBlockInterface $tested): ?FeatureCheckInterface
    {
        foreach ($tested->getChecks() as $check) {
            foreach ($check->getFeatures() as $feature) {
                if ($feature->getVerdict() === FeatureVerdict::NotMet
                    && $feature->getRule()->getMissingRequiredTone() === PropertyTone::Bad
                ) {
                    return $feature;
                }
            }
        }

        return null;
    }

    /**
     * What the row says of the block: why it cannot be read, what it misses, or the summary of its first object
     *
     * @param TestedBlockInterface $tested
     * @param bool $readable
     * @param FeatureCheckInterface|null $failing
     * @return string
     */
    private function summary(TestedBlockInterface $tested, bool $readable, ?FeatureCheckInterface $failing): string
    {
        if (!$readable) {
            return (string) __('The JSON of this block cannot be read, so search engines ignore it.');
        }
        if ($failing !== null) {
            return (string) __(
                '%1: %2 missing.',
                __($failing->getRule()->getLabel()),
                $this->words->join(array_map(
                    static fn (MissingPropertyInterface $property): string => $property->getPath(),
                    $failing->getMissingRequired()
                ))
            );
        }
        $objects = $tested->getObjects();

        return $objects === [] ? '' : $this->summaries->of($tested->getType(), $objects[0]);
    }

    /**
     * The JSON as shown: as the page carries it when it spans lines, else indented
     *
     * @param string $json
     * @param array<mixed>|null $value
     * @return string
     */
    private function shown(string $json, ?array $value): string
    {
        if ($value === null || str_contains($json, "\n")) {
            return $json;
        }
        $indented = json_encode($value, self::SHOWN);

        return $indented === false ? $json : $indented;
    }
}
