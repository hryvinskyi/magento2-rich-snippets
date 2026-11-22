<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\DraftSaveResultInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\StaleSnippetException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Saves a snippet the editor holds
 *
 * A draft of a stored snippet is saved over it, made from the version the editor loaded: a save after someone else
 * saved the snippet is refused, and nothing is written. A new draft is created once per idempotency key, under a code
 * made from its name; a request repeated with the key gets the snippet the first one created.
 *
 * @api
 */
interface SnippetDraftSaveInterface
{
    /**
     * Save a draft
     *
     * @param DraftSnippetInterface $draft
     * @param string|null $expectedUpdatedAt the update time of the version the draft was made from; null saves over
     *     whatever is stored. Ignored for a new draft.
     * @param string|null $idempotencyKey the key of the intent that creates a new draft; null creates without one.
     *     Ignored for a draft of a stored snippet.
     * @return DraftSaveResultInterface the snippet as stored, or the problems that stopped the save
     * @throws NoSuchEntityException when the draft names a snippet that is not stored
     * @throws StaleSnippetException when the stored snippet was saved after the version the draft was made from
     * @throws IdempotencyException when the key is invalid or was used for another kind of write
     */
    public function save(
        DraftSnippetInterface $draft,
        ?string $expectedUpdatedAt,
        ?string $idempotencyKey
    ): DraftSaveResultInterface;
}
