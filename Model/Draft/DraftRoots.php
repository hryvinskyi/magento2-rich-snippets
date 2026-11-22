<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\RichSnippets\Api\CodeAllocatorInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * The snippet a draft is written into: the stored one it edits, or a new one
 *
 * A new snippet to be saved gets its code from the code allocator, made from the draft's name. One that is only
 * checked or previewed gets a stand-in code, so nothing is allocated for a draft that may never be saved. A name
 * the rules refuse gives way to a stand-in name, so the draft's other values are still checked; the refusal comes
 * back when the draft's name is written into the snippet.
 */
class DraftRoots
{
    private const STAND_IN_CODE = 'new-snippet';
    private const STAND_IN_NAME = 'New snippet';

    /**
     * @param SnippetRootRepositoryInterface $repository
     * @param SnippetFactoryInterface $snippets
     * @param CodeAllocatorInterface $codes
     */
    public function __construct(
        private readonly SnippetRootRepositoryInterface $repository,
        private readonly SnippetFactoryInterface $snippets,
        private readonly CodeAllocatorInterface $codes
    ) {
    }

    /**
     * The snippet to check or preview a draft in
     *
     * @param DraftSnippetInterface $draft
     * @return SnippetRootInterface
     * @throws NoSuchEntityException when the draft names a snippet that is not stored
     */
    public function toCheck(DraftSnippetInterface $draft): SnippetRootInterface
    {
        return $this->stored($draft) ?? $this->created(self::STAND_IN_CODE, $draft);
    }

    /**
     * The snippet to save a draft as
     *
     * @param DraftSnippetInterface $draft
     * @return SnippetRootInterface
     * @throws NoSuchEntityException when the draft names a snippet that is not stored
     */
    public function toSave(DraftSnippetInterface $draft): SnippetRootInterface
    {
        return $this->stored($draft) ?? $this->created($this->codes->allocate($draft->getName()), $draft);
    }

    /**
     * Whether a snippet to check is a new one, whose code stands in for the one a save will allocate
     *
     * @param SnippetRootInterface $snippet
     * @return bool
     */
    public function isStandIn(SnippetRootInterface $snippet): bool
    {
        return $snippet->getId() === null && $snippet->getCode() === self::STAND_IN_CODE;
    }

    /**
     * The stored snippet a draft edits; null for a new draft
     *
     * @param DraftSnippetInterface $draft
     * @return SnippetRootInterface|null
     * @throws NoSuchEntityException
     */
    private function stored(DraftSnippetInterface $draft): ?SnippetRootInterface
    {
        $id = $draft->getId();

        return $id === null ? null : $this->repository->get($id);
    }

    /**
     * A new snippet with a code, named as the draft is when the rules allow it
     *
     * @param string $code
     * @param DraftSnippetInterface $draft
     * @return SnippetRootInterface
     */
    private function created(string $code, DraftSnippetInterface $draft): SnippetRootInterface
    {
        try {
            return $this->snippets->draft($code, $draft->getName(), $draft->isBlock());
        } catch (SnippetRuleException) {
            return $this->snippets->draft($code, self::STAND_IN_NAME, $draft->isBlock());
        }
    }
}
