<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\JsonTemplate\Api\Data\CompiledTemplateInterface;
use Hryvinskyi\JsonTemplate\Api\RecordingTraceInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureTallyInterface;
use Hryvinskyi\RichSnippets\Api\Data\MissingPropertyInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputCheckInterface;

/**
 * Compares snippets with what Google publishes for each kind of result
 *
 * The rules come from ResultTypeRulePoolInterface, looked up by the block's types. A block whose root `@type` is a
 * list is checked against the rules of every type in it. A path through a list holds when the list has items and
 * every item has the rest of the path. A missing requirement never stops a snippet from being saved; the checks only
 * report.
 *
 * @api
 */
interface RequirementCheckerInterface
{
    /**
     * Check one evaluated block
     *
     * @param string $type the snippet's schema.org type
     * @param array<mixed> $output the block's evaluated, pruned value
     * @param RecordingTraceInterface|null $trace the trace of the evaluation, to name what was left out and which
     *     variables it read
     * @return OutputCheckInterface
     */
    public function checkOutput(
        string $type,
        array $output,
        ?RecordingTraceInterface $trace = null
    ): OutputCheckInterface;

    /**
     * The required properties a template can never write, whatever the page
     *
     * Read from the compiled template without evaluating it: the type comes from its root `@type` when that is
     * written as text, and a property whose value is a whole expression or an included snippet counts as able to
     * hold anything. A result nested in an object the template never writes is left out and not reported.
     *
     * @param CompiledTemplateInterface $template
     * @return list<MissingPropertyInterface> each with its rule's tone for a missing required property
     */
    public function checkTemplate(CompiledTemplateInterface $template): array;

    /**
     * Count, per kind of result, the blocks that meet it, miss it or leave it out
     *
     * @param list<OutputCheckInterface> $checks
     * @return list<FeatureTallyInterface> one per kind of result met in the checks, in the pool's order
     */
    public function tally(array $checks): array;
}
