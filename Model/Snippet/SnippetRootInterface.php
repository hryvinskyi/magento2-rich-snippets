<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;

/**
 * A snippet as the module changes it: every change goes through a method that keeps the snippet rules
 *
 * A building block is never switched on and has no placement; turning a snippet into one clears both. The type is
 * the first schema.org type of the template's root: retyping rewrites the template, and a changed template brings
 * its own type.
 */
interface SnippetRootInterface extends SnippetInterface
{
    /**
     * Give the snippet another name
     *
     * @param string $name
     * @return void
     * @throws SnippetRuleException when the name is empty, longer than 255 characters or holds a control character
     */
    public function rename(string $name): void;

    /**
     * Replace the template; the type becomes the first type of its root, empty when it has none
     *
     * Whether the template compiles is checked when the snippet is saved.
     *
     * @param string $template
     * @param TemplateTypeInterface $types
     * @return void
     */
    public function changeTemplate(string $template, TemplateTypeInterface $types): void;

    /**
     * Give the snippet another schema.org type, written into the template's root
     *
     * A root whose type is a list keeps its other entries.
     *
     * @param string $type
     * @param TemplateTypeInterface $types
     * @return void
     * @throws SnippetRuleException when the type is outside the type grammar or the template has no object root
     */
    public function retype(string $type, TemplateTypeInterface $types): void;

    /**
     * Take the type from the template's root, refusing a template without one or with one outside the grammar
     *
     * Every entry of a list of types must be in the grammar; the first is the snippet's type.
     *
     * @param TemplateTypeInterface $types
     * @return void
     * @throws SnippetRuleException
     */
    public function projectType(TemplateTypeInterface $types): void;

    /**
     * Set where the snippet is output
     *
     * Repeated kinds count once; a list of store views holding 0 means every store view. Each condition's field,
     * complete or not, must be offered for one of the kinds. No kind at all is allowed: the snippet is then output
     * nowhere. No store view is refused, except for a snippet set for none already (every store view it was set for
     * was deleted): it may be placed again without one and stays switched off.
     *
     * @param list<string> $pageKinds
     * @param ConditionTreeInterface $conditions
     * @param list<int> $storeIds
     * @param PlacementVocabularyInterface $vocabulary
     * @return void
     * @throws SnippetRuleException
     */
    public function place(
        array $pageKinds,
        ConditionTreeInterface $conditions,
        array $storeIds,
        PlacementVocabularyInterface $vocabulary
    ): void;

    /**
     * Switch the snippet on
     *
     * @return void
     * @throws SnippetRuleException when the snippet is a building block or is set for no store view
     */
    public function switchOn(): void;

    /**
     * Stop outputting the snippet in a store view that is gone
     *
     * The store view leaves the snippet's list. A snippet left with no store view is switched off and output nowhere
     * until it is placed again: it never falls back to every store view. A snippet set for every store view, or not
     * for this one, is left as it is.
     *
     * @param int $storeId
     * @return void
     */
    public function leaveStoreView(int $storeId): void;

    /**
     * Refuse this snippet as the new state of a stored one when it would leave a store view the shop still has
     *
     * Only the deletion of store views may leave a snippet set for none: a stored snippet set for a store view the
     * shop has, or for every store view, is never saved with none. A stored snippet already set for none, or only
     * for store views that are gone, may be saved with none.
     *
     * @param SnippetInterface $stored the snippet as it is stored
     * @param ExistingStoreViewsInterface $storeViews
     * @return void
     * @throws SnippetRuleException when this snippet is set for no store view while the stored one is set for one the
     *     shop has
     */
    public function assertKeepsStoreViewsOf(SnippetInterface $stored, ExistingStoreViewsInterface $storeViews): void;

    /**
     * Switch the snippet off
     *
     * @return void
     */
    public function switchOff(): void;

    /**
     * Make the snippet a building block: switched off, with no kinds of page, conditions or store views of its own
     *
     * @param ConditionTreeInterface $noConditions the empty tree the block keeps in place of its conditions
     * @return void
     * @throws SnippetRuleException when the tree given holds a condition
     */
    public function turnIntoBlock(ConditionTreeInterface $noConditions): void;

    /**
     * Make a building block a page snippet, switched off and output nowhere until it is placed
     *
     * @return void
     */
    public function turnIntoPageSnippet(): void;

    /**
     * Give the snippet another place in the output order
     *
     * @param int $sortOrder
     * @return void
     * @throws SnippetRuleException when the sort order is negative
     */
    public function moveTo(int $sortOrder): void;

    /**
     * Record the library template the snippet was added from
     *
     * @param OriginInterface|null $origin null forgets it
     * @return void
     */
    public function recordOrigin(?OriginInterface $origin): void;
}
