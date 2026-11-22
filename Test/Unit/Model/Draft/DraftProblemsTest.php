<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Draft;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Engine\Data\CompileError;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Model\Draft\DraftProblems;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\DraftFactoryFake;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the engine and the snippet rules refuse becomes a problem addressed to the part of the draft it concerns
 */
class DraftProblemsTest extends TestCase
{
    public function testEveryCompileErrorIsATemplateProblemAtItsPath(): void
    {
        $problems = $this->problems()->fromCompile(new CompileException([
            new CompileError('$.name', 'unknown-filter', ['filter' => 'shout'], 14),
            new CompileError('$', 'invalid-json', [], null),
        ]));

        self::assertSame(
            [
                ['template', '$.name', 'unknown-filter', ['filter' => 'shout'], 14, true],
                ['template', '$', 'invalid-json', [], null, true],
            ],
            array_map([$this, 'row'], $problems)
        );
    }

    /**
     * @return array<string, array{0: SnippetRuleReason, 1: string}>
     */
    public static function refusals(): array
    {
        return [
            'a name' => [SnippetRuleReason::NameInvalid, DraftProblemInterface::PART_NAME],
            'no type' => [SnippetRuleReason::TypeMissing, DraftProblemInterface::PART_TYPE],
            'a type outside the grammar' => [SnippetRuleReason::TypeInvalid, DraftProblemInterface::PART_TYPE],
            'a template without an object' => [
                SnippetRuleReason::TemplateNotAnObject,
                DraftProblemInterface::PART_TEMPLATE,
            ],
            'a loop' => [SnippetRuleReason::IncludeLoop, DraftProblemInterface::PART_TEMPLATE],
            'a kind of page' => [SnippetRuleReason::PageKindUnknown, DraftProblemInterface::PART_PAGE_KINDS],
            'no store view' => [SnippetRuleReason::StoresMissing, DraftProblemInterface::PART_STORES],
            'a code taken meanwhile' => [SnippetRuleReason::CodeTaken, DraftProblemInterface::PART_SNIPPET],
        ];
    }

    #[DataProvider('refusals')]
    public function testARefusalConcernsThePartItsRuleGuards(SnippetRuleReason $reason, string $part): void
    {
        $problem = $this->problems()->fromRule(new SnippetRuleException($reason, ['kind' => 'blog']));

        self::assertSame([$part, '$', $reason->value, ['kind' => 'blog'], null, true], $this->row($problem));
    }

    /**
     * @return array<string, array{SnippetRuleReason}>
     */
    public static function conditionRefusals(): array
    {
        return [
            'a field of another kind' => [SnippetRuleReason::ConditionFieldOutsideKinds],
            'an unknown field' => [SnippetRuleReason::ConditionFieldUnknown],
            'an unknown attribute' => [SnippetRuleReason::ConditionAttributeUnknown],
            'an operator the field lacks' => [SnippetRuleReason::ConditionOperatorUnknown],
            'groups nested too deep' => [SnippetRuleReason::ConditionsTooDeep],
            'a tree that does not read' => [SnippetRuleReason::ConditionInvalid],
        ];
    }

    #[DataProvider('conditionRefusals')]
    public function testARefusedConditionConcernsTheConditionsAsAWhole(SnippetRuleReason $reason): void
    {
        $problem = $this->problems()->fromRule(new SnippetRuleException($reason));

        self::assertSame(['conditions', '$'], [$problem->getPart(), $problem->getPath()]);
    }

    public function testAPageSnippetOutputNowhereIsAProblemThatDoesNotStopASave(): void
    {
        self::assertSame(
            ['pageKinds', '$', 'no_page_kinds', [], null, false],
            $this->row($this->problems()->noPageKinds())
        );
    }

    public function testALoopNamesItsSnippetsInTheOrderTheSaveRefusesThem(): void
    {
        self::assertSame(
            ['template', '$', 'include_loop', ['codes' => 'policy → product → policy'], null, true],
            $this->row($this->problems()->includeLoop(['policy', 'product', 'policy']))
        );
    }

    /**
     * A problem's values, in the order of its getters
     *
     * @param DraftProblemInterface $problem
     * @return list<mixed>
     */
    public function row(DraftProblemInterface $problem): array
    {
        return [
            $problem->getPart(),
            $problem->getPath(),
            $problem->getCode(),
            $problem->getParams(),
            $problem->getOffset(),
            $problem->isBlocking(),
        ];
    }

    /**
     * @return DraftProblems
     */
    private function problems(): DraftProblems
    {
        return new DraftProblems(new DraftFactoryFake());
    }
}
