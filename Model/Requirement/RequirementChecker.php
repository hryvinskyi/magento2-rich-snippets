<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\RecordingTraceInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureVerdict;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\RequirementInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\RequirementCheckerInterface;
use Hryvinskyi\RichSnippets\Api\ResultTypeRulePoolInterface;

/**
 * Checks blocks and templates against the rules of their types, looked up in the rule pool
 *
 * For each rule that applies to one of the block's types, at the anchor it names:
 * - a nested result whose object the block does not carry is left out, with nothing reported missing;
 * - each required and each recommended property the block lacks is reported, the required ones in the rule's tone
 *   for them, the recommended ones as a warning; an any-of group counts as present when one member is;
 * - the verdict is the rule's for the number of missing required properties.
 * A missing any-of group is named by the member the template wrote and the page left empty, when the trace shows
 * one, else by the group's first member. Paths are reported from the block's root, anchor included.
 *
 * @phpstan-import-type LeftOutEntries from TraceReading
 */
class RequirementChecker implements RequirementCheckerInterface
{
    private const TYPE = '@type';

    /**
     * @param ResultTypeRulePoolInterface $rules
     * @param OutputPaths $outputPaths
     * @param TemplateShape $templateShape
     * @param TraceReading $traceReading
     * @param CheckFactoryInterface $factory
     */
    public function __construct(
        private readonly ResultTypeRulePoolInterface $rules,
        private readonly OutputPaths $outputPaths,
        private readonly TemplateShape $templateShape,
        private readonly TraceReading $traceReading,
        private readonly CheckFactoryInterface $factory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function checkOutput(
        string $type,
        array $output,
        ?RecordingTraceInterface $trace = null
    ): OutputCheckInterface {
        $leftOut = $trace === null ? [] : $this->traceReading->leftOut($trace);
        $has = fn (string $path): bool => $this->outputPaths->has($output, $path);
        $features = [];
        foreach ($this->applying($this->typesOf($type, $output)) as [$rule, $anchor]) {
            $features[] = $this->feature($rule, $anchor, $has, $leftOut);
        }
        $properties = [];
        foreach ($this->traceReading->outermost($leftOut) as $path => $entry) {
            $properties[] = $this->factory->leftOut($path, $entry['outcome'], $entry['sources']);
        }

        return $this->factory->output($type, $features, $properties);
    }

    /**
     * @inheritDoc
     */
    public function checkTemplate(CompiledTemplateInterface $template): array
    {
        $shape = $this->templateShape->read($template);
        $has = fn (string $path): bool => $this->templateShape->has($shape, $path);
        $missing = [];
        foreach ($this->applying($shape['types']) as [$rule, $anchor]) {
            if ($anchor !== '' && !$has($anchor)) {
                continue;
            }
            $missing = [
                ...$missing,
                ...$this->missing($rule, $rule->getRequired(), $anchor, $rule->getMissingRequiredTone(), $has, []),
            ];
        }

        return $missing;
    }

    /**
     * @inheritDoc
     */
    public function tally(array $checks): array
    {
        $counts = [];
        foreach ($checks as $check) {
            foreach ($check->getFeatures() as $feature) {
                $code = $feature->getRule()->getCode();
                $counts[$code] ??= [FeatureVerdict::Met->value => 0, FeatureVerdict::NotMet->value => 0,
                    FeatureVerdict::LeftOut->value => 0];
                $counts[$code][$feature->getVerdict()->value]++;
            }
        }
        $tallies = [];
        foreach ($this->rules->all() as $rule) {
            $count = $counts[$rule->getCode()] ?? null;
            if ($count !== null) {
                $tallies[] = $this->factory->tally(
                    $rule,
                    $count[FeatureVerdict::Met->value],
                    $count[FeatureVerdict::NotMet->value],
                    $count[FeatureVerdict::LeftOut->value]
                );
            }
        }

        return $tallies;
    }

    /**
     * One block against one rule
     *
     * @param ResultTypeRuleInterface $rule
     * @param string $anchor
     * @param \Closure(string):bool $has
     * @param LeftOutEntries $leftOut
     * @return FeatureCheckInterface
     */
    private function feature(
        ResultTypeRuleInterface $rule,
        string $anchor,
        \Closure $has,
        array $leftOut
    ): FeatureCheckInterface {
        if ($anchor !== '' && !$has($anchor)) {
            return $this->factory->feature($rule, FeatureVerdict::LeftOut, [], []);
        }
        $requiredTone = $rule->getMissingRequiredTone();
        $required = $this->missing($rule, $rule->getRequired(), $anchor, $requiredTone, $has, $leftOut);
        $recommended = $this->missing($rule, $rule->getRecommended(), $anchor, PropertyTone::Warn, $has, $leftOut);

        return $this->factory->feature($rule, $rule->verdictFor(count($required)), $required, $recommended);
    }

    /**
     * The requirements a block or template lacks
     *
     * @param ResultTypeRuleInterface $rule
     * @param list<RequirementInterface> $requirements
     * @param string $anchor
     * @param PropertyTone $tone
     * @param \Closure(string):bool $has
     * @param LeftOutEntries $leftOut
     * @return list<MissingPropertyInterface>
     */
    private function missing(
        ResultTypeRuleInterface $rule,
        array $requirements,
        string $anchor,
        PropertyTone $tone,
        \Closure $has,
        array $leftOut
    ): array {
        $missing = [];
        foreach ($requirements as $requirement) {
            $paths = array_map(
                static fn (string $path): string => $anchor === '' ? $path : $anchor . '.' . $path,
                $requirement->getPaths()
            );
            if (array_filter($paths, $has) !== []) {
                continue;
            }
            $named = $paths[0];
            foreach ($paths as $path) {
                if (isset($leftOut[$path])) {
                    $named = $path;
                    break;
                }
            }
            $missing[] = $this->factory->missing(
                $rule->getCode(),
                $named,
                $tone,
                $this->traceReading->sourcesFor($leftOut, $named)
            );
        }

        return $missing;
    }

    /**
     * The rules that apply to any of the types, each once with its anchor, in the pool's order
     *
     * @param list<string> $types
     * @return list<array{ResultTypeRuleInterface, string}>
     */
    private function applying(array $types): array
    {
        $applying = [];
        foreach ($this->rules->all() as $rule) {
            foreach ($types as $type) {
                $anchor = $rule->anchorFor($type);
                if ($anchor !== null) {
                    $applying[] = [$rule, $anchor];
                    break;
                }
            }
        }

        return $applying;
    }

    /**
     * The type the check was asked for, then any other type the block's root `@type` names
     *
     * @param string $type
     * @param array<mixed> $output
     * @return list<string>
     */
    private function typesOf(string $type, array $output): array
    {
        $written = $output[self::TYPE] ?? [];
        $types = [$type];
        foreach (is_array($written) ? $written : [$written] as $name) {
            if (is_string($name) && $name !== '' && !in_array($name, $types, true)) {
                $types[] = $name;
            }
        }

        return $types;
    }
}
