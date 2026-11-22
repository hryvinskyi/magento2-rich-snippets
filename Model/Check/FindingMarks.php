<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;

/**
 * The gaps one checked block shows, each named by its key
 *
 * A result the block leaves out is one gap, worth a note, whatever it lacks. Otherwise every required and every
 * recommended property the block misses is a gap of its own, with the tone its rule gives the missing property and
 * the variables the template read for it.
 *
 * @phpstan-type Mark array{key: string, tone: PropertyTone, rule: string, path: string, sources: list<string>}
 */
class FindingMarks
{
    /**
     * @param FindingKeys $keys
     */
    public function __construct(private readonly FindingKeys $keys)
    {
    }

    /**
     * The gaps of a block, in the order of its results and their properties
     *
     * @param OutputCheckInterface $check
     * @return list<Mark>
     */
    public function of(OutputCheckInterface $check): array
    {
        $marks = [];
        foreach ($check->getFeatures() as $feature) {
            if ($feature->getVerdict() === FeatureVerdict::LeftOut) {
                $marks[] = $this->leftOut($feature);
                continue;
            }
            foreach ([...$feature->getMissingRequired(), ...$feature->getMissingRecommended()] as $missing) {
                $marks[] = $this->missing($feature->getRule()->getCode(), $missing);
            }
        }

        return $marks;
    }

    /**
     * The gap of a result left out
     *
     * @param FeatureCheckInterface $feature
     * @return Mark
     */
    private function leftOut(FeatureCheckInterface $feature): array
    {
        $rule = $feature->getRule()->getCode();
        $sources = [];
        foreach ($feature->getMissingRequired() as $missing) {
            array_push($sources, ...$missing->getSources());
        }
        $sources = array_values(array_unique($sources));

        return [
            'key' => $this->keys->key($rule, '', $sources),
            'tone' => PropertyTone::Note,
            'rule' => $rule,
            'path' => '',
            'sources' => $sources,
        ];
    }

    /**
     * The gap of a missing property
     *
     * @param string $rule
     * @param MissingPropertyInterface $missing
     * @return Mark
     */
    private function missing(string $rule, MissingPropertyInterface $missing): array
    {
        $sources = array_values(array_unique($missing->getSources()));

        return [
            'key' => $this->keys->key($rule, $missing->getPath(), $sources),
            'tone' => $missing->getTone(),
            'rule' => $rule,
            'path' => $missing->getPath(),
            'sources' => $sources,
        ];
    }
}
