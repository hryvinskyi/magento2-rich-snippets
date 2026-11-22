<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;

/**
 * Turns what the engine and the snippet rules refuse into problems addressed to the part of the draft they concern
 *
 * A refusal of the conditions points at the conditions as a whole: the builder shows its own conditions, and the
 * refusal's parameters name the field, attribute or operator it is about.
 */
class DraftProblems
{
    private const WHOLE = '$';
    private const LOOP_SEPARATOR = ' → ';

    /**
     * @param DraftFactoryInterface $factory
     */
    public function __construct(private readonly DraftFactoryInterface $factory)
    {
    }

    /**
     * One problem for every error of a template that does not compile, in the order the compiler met them
     *
     * @param CompileException $exception
     * @return list<DraftProblemInterface>
     */
    public function fromCompile(CompileException $exception): array
    {
        $problems = [];
        foreach ($exception->getErrors() as $error) {
            $problems[] = $this->factory->problem(
                DraftProblemInterface::PART_TEMPLATE,
                $error->getPath(),
                $error->getCode(),
                $error->getParams(),
                $error->getOffset()
            );
        }

        return $problems;
    }

    /**
     * The problem a snippet rule refused a value with
     *
     * @param SnippetRuleException $exception
     * @return DraftProblemInterface
     */
    public function fromRule(SnippetRuleException $exception): DraftProblemInterface
    {
        $reason = $exception->getReason();

        return $this->factory->problem($this->partOf($reason), self::WHOLE, $reason->value, $exception->getParams());
    }

    /**
     * The problem of a template whose includes would lead back to the snippet itself
     *
     * @param list<string> $loop the codes of the loop, from the snippet back to it, as the save refuses it
     * @return DraftProblemInterface
     */
    public function includeLoop(array $loop): DraftProblemInterface
    {
        return $this->factory->problem(
            DraftProblemInterface::PART_TEMPLATE,
            self::WHOLE,
            SnippetRuleReason::IncludeLoop->value,
            ['codes' => implode(self::LOOP_SEPARATOR, $loop)]
        );
    }

    /**
     * The problem of a page snippet that is output nowhere, which does not stop a save
     *
     * @return DraftProblemInterface
     */
    public function noPageKinds(): DraftProblemInterface
    {
        return $this->factory->problem(
            DraftProblemInterface::PART_PAGE_KINDS,
            self::WHOLE,
            DraftProblemInterface::NO_PAGE_KINDS,
            [],
            null,
            false
        );
    }

    /**
     * The part of a draft a snippet rule's refusal concerns
     *
     * @param SnippetRuleReason $reason
     * @return string
     */
    private function partOf(SnippetRuleReason $reason): string
    {
        return match ($reason) {
            SnippetRuleReason::NameInvalid => DraftProblemInterface::PART_NAME,
            SnippetRuleReason::TypeMissing, SnippetRuleReason::TypeInvalid => DraftProblemInterface::PART_TYPE,
            SnippetRuleReason::TemplateNotAnObject,
            SnippetRuleReason::IncludeLoop => DraftProblemInterface::PART_TEMPLATE,
            SnippetRuleReason::PageKindUnknown => DraftProblemInterface::PART_PAGE_KINDS,
            SnippetRuleReason::ConditionFieldUnknown,
            SnippetRuleReason::ConditionFieldOutsideKinds,
            SnippetRuleReason::ConditionInvalid,
            SnippetRuleReason::ConditionAttributeUnknown,
            SnippetRuleReason::ConditionOperatorUnknown,
            SnippetRuleReason::ConditionsTooDeep => DraftProblemInterface::PART_CONDITIONS,
            SnippetRuleReason::StoresMissing, SnippetRuleReason::StoreInvalid => DraftProblemInterface::PART_STORES,
            SnippetRuleReason::CodeInvalid,
            SnippetRuleReason::CodeTaken,
            SnippetRuleReason::CodeChanged,
            SnippetRuleReason::BlockCannotBeSwitchedOn,
            SnippetRuleReason::BlockHasNoPlacement,
            SnippetRuleReason::SortOrderInvalid,
            SnippetRuleReason::OriginInvalid => DraftProblemInterface::PART_SNIPPET,
        };
    }
}
