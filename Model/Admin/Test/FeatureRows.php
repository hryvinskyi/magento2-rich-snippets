<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test;

use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Data\TestedBlockInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Feature\VerdictWordingInterface;

/**
 * Writes the page's standing against Google's requirements as the "Against Google’s requirements" panel lists it:
 * one entry per kind of result the page's blocks can have, in the order the blocks first name them
 *
 * Several blocks may try for one kind of result (two snippets of one type on a page); their verdicts merge into one
 * entry. The worst verdict of the blocks that try for the result wins — a result one block misses is not met — and a
 * block that leaves the result out counts only when every block does; the entry lists the missing properties of the
 * blocks behind the winning verdict, each once, in the order they are first named.
 *
 * Each verdict is worded by its wording in the pool: a met result says what it has, then either that every
 * recommended value is there too or which are not; a result not met lists the required properties it misses; a
 * result left out says why when its rule says so. A result Google no longer shows says that instead, whatever the
 * verdict. A verdict without a wording is an error in the configuration and fails loudly.
 *
 * @phpstan-type Feature array{rule: string, label: string, verdict: string, description: string,
 *     missingRequired: list<string>, missingRecommended: list<string>}
 * @phpstan-type Standing array{rule: ResultTypeRuleInterface, verdict: FeatureVerdict,
 *     required: array<string, true>, recommended: array<string, true>}
 */
class FeatureRows
{
    /** How bad each verdict is when blocks merge: a block that leaves the result out does not try for it */
    private const SEVERITY = [
        'left_out' => 0,
        'met' => 1,
        'not_met' => 2,
    ];

    /**
     * @param array<string, VerdictWordingInterface> $wordings by the verdict's value
     */
    public function __construct(private readonly array $wordings = [])
    {
    }

    /**
     * The entries of a page's blocks
     *
     * @param list<TestedBlockInterface> $blocks
     * @return list<Feature>
     * @throws \LogicException when no wording is configured for a verdict
     */
    public function rows(array $blocks): array
    {
        $standings = [];
        foreach ($blocks as $block) {
            foreach ($block->getChecks() as $check) {
                foreach ($check->getFeatures() as $feature) {
                    $code = $feature->getRule()->getCode();
                    $standings[$code] = $this->merged($standings[$code] ?? null, $feature);
                }
            }
        }

        return array_values(array_map(fn (array $standing): array => $this->row($standing), $standings));
    }

    /**
     * A standing with one more block's verdict on the same kind of result
     *
     * @param Standing|null $standing null for the first block that names the result
     * @param FeatureCheckInterface $feature
     * @return Standing
     */
    private function merged(?array $standing, FeatureCheckInterface $feature): array
    {
        $verdict = $feature->getVerdict();
        $own = [
            'rule' => $feature->getRule(),
            'verdict' => $verdict,
            'required' => $this->paths($feature->getMissingRequired()),
            'recommended' => $this->paths($feature->getMissingRecommended()),
        ];
        if ($standing === null) {
            return $own;
        }
        $severity = self::SEVERITY[$verdict->value];
        $current = self::SEVERITY[$standing['verdict']->value];
        if ($severity > $current) {
            return $own;
        }
        if ($severity < $current) {
            return $standing;
        }
        $standing['required'] += $own['required'];
        $standing['recommended'] += $own['recommended'];

        return $standing;
    }

    /**
     * One entry
     *
     * @param Standing $standing
     * @return Feature
     * @throws \LogicException
     */
    private function row(array $standing): array
    {
        $rule = $standing['rule'];
        $recommended = array_map('strval', array_keys($standing['recommended']));

        return [
            'rule' => $rule->getCode(),
            'label' => (string) __($rule->getLabel()),
            'verdict' => $standing['verdict']->value,
            'description' => $rule->getGoogleStatus() !== GoogleStatus::Ok
                ? (string) __($rule->getStatusText())
                : $this->wording($standing['verdict'])->describe($rule, $recommended),
            'missingRequired' => array_map('strval', array_keys($standing['required'])),
            'missingRecommended' => $recommended,
        ];
    }

    /**
     * The wording of a verdict
     *
     * @param FeatureVerdict $verdict
     * @return VerdictWordingInterface
     * @throws \LogicException when no wording is configured for the verdict
     */
    private function wording(FeatureVerdict $verdict): VerdictWordingInterface
    {
        $wording = $this->wordings[$verdict->value] ?? null;
        if (!$wording instanceof VerdictWordingInterface) {
            throw new \LogicException(sprintf('No wording is configured for the verdict "%s".', $verdict->value));
        }

        return $wording;
    }

    /**
     * The paths of missing properties, each once, in order
     *
     * @param list<MissingPropertyInterface> $missing
     * @return array<string, true>
     */
    private function paths(array $missing): array
    {
        $paths = [];
        foreach ($missing as $property) {
            $paths[$property->getPath()] = true;
        }

        return $paths;
    }
}
