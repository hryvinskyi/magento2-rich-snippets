<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\DraftPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Checks and previews a snippet the editor holds, without saving it
 *
 * Both read the draft against the stored snippets: a draft of a stored snippet keeps that snippet's code, so a loop
 * through the snippets that include it is found before it is saved.
 *
 * @api
 */
interface SnippetDraftInterface
{
    /**
     * Everything wrong with a draft, the template's problems first
     *
     * @param DraftSnippetInterface $draft
     * @return list<DraftProblemInterface> none for a draft that can be saved as it is
     * @throws NoSuchEntityException when the draft names a snippet that is not stored
     */
    public function validate(DraftSnippetInterface $draft): array;

    /**
     * The draft evaluated for a sample page, as the storefront of the page's store view evaluates it
     *
     * The URLs, prices and settings are the storefront's, for a guest in the store view's default currency. The
     * page's kind and store view are not compared with the draft's: the sample is the admin's choice.
     *
     * @param DraftSnippetInterface $draft
     * @param PageEntityInterface $page
     * @return DraftPreviewInterface
     * @throws NoSuchEntityException when the draft names a snippet that is not stored
     */
    public function preview(DraftSnippetInterface $draft, PageEntityInterface $page): DraftPreviewInterface;
}
