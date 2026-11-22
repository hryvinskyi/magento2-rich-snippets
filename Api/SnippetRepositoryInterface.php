<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetInUseException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * The shop's snippets: read, saved and deleted under the snippet rules
 *
 * A save refuses a template that does not compile and one that would make the snippet include itself, takes the
 * snippet's type from the template's root, and cleans the page cache of the pages the change reaches once the
 * change is committed. A delete is refused while another snippet includes the snippet.
 *
 * @api
 */
interface SnippetRepositoryInterface
{
    /**
     * The snippet with an ID
     *
     * @param int $id
     * @return SnippetInterface
     * @throws NoSuchEntityException when no snippet has the ID
     */
    public function get(int $id): SnippetInterface;

    /**
     * The snippet with a code
     *
     * @param string $code
     * @return SnippetInterface
     * @throws NoSuchEntityException when no snippet has the code
     */
    public function getByCode(string $code): SnippetInterface;

    /**
     * The snippets that meet every criterion given, by sort order, then ID
     *
     * @param int|null $storeId only snippets output in the store view: those listing it or every store view
     * @param string|null $pageKind only snippets output on the kind of page
     * @param bool|null $active only snippets switched on (true) or off (false)
     * @param bool|null $block only building blocks (true) or page snippets (false)
     * @return list<SnippetInterface>
     */
    public function list(
        ?int $storeId = null,
        ?string $pageKind = null,
        ?bool $active = null,
        ?bool $block = null
    ): array;

    /**
     * Save a snippet: a new one when it has no ID, else the stored one with its ID
     *
     * @param SnippetInterface $snippet
     * @return SnippetInterface the snippet as stored, with its ID, type and time of the save
     * @throws CompileException when the template does not compile
     * @throws SnippetRuleException when the snippet breaks a snippet rule
     * @throws NoSuchEntityException when the snippet has an ID no stored snippet has
     */
    public function save(SnippetInterface $snippet): SnippetInterface;

    /**
     * Delete a stored snippet
     *
     * @param SnippetInterface $snippet
     * @return void
     * @throws SnippetInUseException when another snippet includes it
     * @throws NoSuchEntityException when the snippet is not stored
     */
    public function delete(SnippetInterface $snippet): void;
}
