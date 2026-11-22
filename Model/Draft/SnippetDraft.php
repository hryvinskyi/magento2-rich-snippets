<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\JsonTemplate\Api\RecordingTraceFactoryInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\ConditionSentenceInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PreviewState;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Hryvinskyi\RichSnippets\Api\RequirementCheckerInterface;
use Hryvinskyi\RichSnippets\Api\SnippetDraftInterface;
use Hryvinskyi\RichSnippets\Api\SnippetSelectorInterface;
use Hryvinskyi\RichSnippets\Model\Engine\TemplatePrefetch;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementConditions;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;

/**
 * Checks and previews the editor's drafts
 *
 * A preview evaluates the draft the way the storefront evaluates a stored snippet: one batch of the page's data for
 * the template and its includes, then one evaluation with a recording trace, inside the store view's storefront. It
 * shows nothing for a template without a type or one that does not compile, and nothing for a page that fails the
 * draft's conditions (conditions that do not read are left to the draft's problems); a missing name or kind of
 * page does not stop it. A building block appears as it does inside the snippet that includes it, without a root
 * `@context`.
 */
class SnippetDraft implements SnippetDraftInterface
{
    private const CONTEXT = '@context';
    private const SHOWN_PARTS = [DraftProblemInterface::PART_TEMPLATE, DraftProblemInterface::PART_TYPE];

    /**
     * @param DraftRoots $roots
     * @param DraftValidation $validation
     * @param DraftFactoryInterface $factory
     * @param PlacementConditions $conditions
     * @param SnippetSelectorInterface $selector
     * @param TemplateEngineInterface $engine the snippets' own engine
     * @param TemplatePrefetch $prefetch
     * @param PageScopeFactoryInterface $scopes
     * @param RecordingTraceFactoryInterface $traces
     * @param FrontendEmulationInterface $emulation
     * @param RequirementCheckerInterface $checker
     * @param ConditionSentenceInterface $sentence
     */
    public function __construct(
        private readonly DraftRoots $roots,
        private readonly DraftValidation $validation,
        private readonly DraftFactoryInterface $factory,
        private readonly PlacementConditions $conditions,
        private readonly SnippetSelectorInterface $selector,
        private readonly TemplateEngineInterface $engine,
        private readonly TemplatePrefetch $prefetch,
        private readonly PageScopeFactoryInterface $scopes,
        private readonly RecordingTraceFactoryInterface $traces,
        private readonly FrontendEmulationInterface $emulation,
        private readonly RequirementCheckerInterface $checker,
        private readonly ConditionSentenceInterface $sentence
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validate(DraftSnippetInterface $draft): array
    {
        return $this->validation->check($this->roots->toCheck($draft), $draft);
    }

    /**
     * @inheritDoc
     */
    public function preview(DraftSnippetInterface $draft, PageEntityInterface $page): DraftPreviewInterface
    {
        $snippet = $this->roots->toCheck($draft);
        $problems = $this->shown($this->validation->check($snippet, $draft));
        if ($problems !== []) {
            return $this->factory->preview(
                $this->hasNoType($problems) ? PreviewState::NoType : PreviewState::Invalid,
                $page,
                null,
                [],
                [],
                [],
                null,
                $problems
            );
        }
        if (!$draft->isBlock()) {
            $mismatches = $this->conditions->mismatches($snippet->getConditions(), $page);
            if ($mismatches !== []) {
                return $this->factory->preview(
                    PreviewState::ConditionsNotMet,
                    $page,
                    null,
                    [],
                    $mismatches,
                    $this->instead($snippet, $page),
                    null,
                    [],
                    $this->sentence->of($snippet->getConditions(), $page->getStoreId())
                );
            }
        }

        return $this->emulation->run(
            $page->getStoreId(),
            fn (): DraftPreviewInterface => $this->evaluate($snippet, $page)
        );
    }

    /**
     * The draft evaluated on the page, with its trace and its check
     *
     * @param SnippetRootInterface $snippet the draft written into its snippet
     * @param PageEntityInterface $page
     * @return DraftPreviewInterface
     */
    private function evaluate(SnippetRootInterface $snippet, PageEntityInterface $page): DraftPreviewInterface
    {
        $template = $snippet->getTemplate();
        $this->prefetch->forPage($page, [$this->engine->compile($template)]);
        $trace = $this->traces->create();
        $value = $this->engine->evaluate($template, $this->scopes->create($page), $trace);
        if ($snippet->isBlock() && is_array($value)) {
            unset($value[self::CONTEXT]);
        }
        if (!is_array($value) || $value === []) {
            return $this->factory->preview(PreviewState::Empty, $page, null, $trace->getEntries());
        }

        return $this->factory->preview(
            PreviewState::Output,
            $page,
            $value,
            $trace->getEntries(),
            [],
            [],
            $this->checker->checkOutput($snippet->getType(), $value, $trace)
        );
    }

    /**
     * The stored page snippets of the draft's type the page writes, other than the draft's own
     *
     * @param SnippetRootInterface $snippet
     * @param PageEntityInterface $page
     * @return list<SnippetInterface>
     */
    private function instead(SnippetRootInterface $snippet, PageEntityInterface $page): array
    {
        return array_values(array_filter(
            $this->selector->select($page),
            static fn (SnippetInterface $other): bool => $other->getType() === $snippet->getType()
                && $other->getId() !== $snippet->getId()
        ));
    }

    /**
     * The problems that stop a preview: the template's and the type's that would stop a save
     *
     * @param list<DraftProblemInterface> $problems
     * @return list<DraftProblemInterface>
     */
    private function shown(array $problems): array
    {
        return array_values(array_filter(
            $this->validation->blocking($problems),
            static fn (DraftProblemInterface $problem): bool => in_array(
                $problem->getPart(),
                self::SHOWN_PARTS,
                true
            )
        ));
    }

    /**
     * Whether the only thing in the way is a template whose root has no type yet
     *
     * @param list<DraftProblemInterface> $problems
     * @return bool
     */
    private function hasNoType(array $problems): bool
    {
        foreach ($problems as $problem) {
            if ($problem->getCode() !== SnippetRuleReason::TypeMissing->value) {
                return false;
            }
        }

        return true;
    }
}
