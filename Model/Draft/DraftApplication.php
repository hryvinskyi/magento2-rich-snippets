<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionTreeFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;

/**
 * Writes a draft's values into a snippet through the snippet's own rules, keeping every refusal as a problem
 *
 * Each value is tried on its own, so one refused value does not hide the others. A building block drops its
 * placement and its switch; a page snippet is placed and switched as the draft says, its conditions read from the
 * builder's text (a text that does not read is a problem of the conditions). The type is read from the
 * template, so a template that is JSON without an object at its root is a problem of the template; one that is not
 * JSON at all is left to the compiler, which says where.
 */
class DraftApplication
{
    /**
     * @param TemplateTypeInterface $types
     * @param PlacementVocabularyInterface $vocabulary
     * @param DraftProblems $problems
     * @param ConditionTreeParserInterface $parser
     * @param ConditionTreeFactoryInterface $trees
     */
    public function __construct(
        private readonly TemplateTypeInterface $types,
        private readonly PlacementVocabularyInterface $vocabulary,
        private readonly DraftProblems $problems,
        private readonly ConditionTreeParserInterface $parser,
        private readonly ConditionTreeFactoryInterface $trees
    ) {
    }

    /**
     * Write a draft into a snippet
     *
     * @param SnippetRootInterface $snippet changed in place
     * @param DraftSnippetInterface $draft
     * @return list<DraftProblemInterface> the refusals, in the order the values were tried
     */
    public function apply(SnippetRootInterface $snippet, DraftSnippetInterface $draft): array
    {
        $problems = [];
        $this->attempt($problems, $draft, fn () => $snippet->rename($draft->getName()));
        $snippet->changeTemplate($draft->getTemplate(), $this->types);
        $this->attempt($problems, $draft, fn () => $this->projectType($snippet, $draft->getTemplate()));
        if ($draft->isBlock()) {
            $snippet->turnIntoBlock($this->trees->tree());

            return $problems;
        }
        if ($snippet->isBlock()) {
            $snippet->turnIntoPageSnippet();
        }
        $this->attempt($problems, $draft, fn () => $snippet->place(
            $draft->getPageKinds(),
            $this->parser->parse($draft->getConditions()),
            $draft->getStoreIds(),
            $this->vocabulary
        ));
        if ($draft->isActive()) {
            $this->attempt($problems, $draft, fn () => $snippet->switchOn());
        } else {
            $snippet->switchOff();
        }

        return $problems;
    }

    /**
     * Take the type from the template's root, when the template is JSON at all
     *
     * @param SnippetRootInterface $snippet
     * @param string $template
     * @return void
     * @throws SnippetRuleException
     */
    private function projectType(SnippetRootInterface $snippet, string $template): void
    {
        $decoded = json_decode($template);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return;
        }
        if (!$decoded instanceof \stdClass) {
            throw new SnippetRuleException(SnippetRuleReason::TemplateNotAnObject);
        }
        $snippet->projectType($this->types);
    }

    /**
     * Run a change, keeping its refusal as a problem
     *
     * @param list<DraftProblemInterface> $problems
     * @param DraftSnippetInterface $draft
     * @param callable():void $change
     * @return void
     */
    private function attempt(array &$problems, DraftSnippetInterface $draft, callable $change): void
    {
        try {
            $change();
        } catch (SnippetRuleException $e) {
            $problems[] = $this->problems->fromRule($e);
        }
    }
}
