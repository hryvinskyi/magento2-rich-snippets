<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\SnippetExportFileInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Writes snippets into a file another shop (or this one) can import
 *
 * The document is `{"hryvinskyi_rich_snippets": {"version": 1, "exportedAt", "source"}, "snippets": [snippet]}`,
 * the header naming when and from which host it was exported. Each snippet is `{"code", "name", "type", "block",
 * "active", "pageKinds", "match", "conditions", "stores", "sortOrder", "origin", "updatedAt", "template"}`. Nothing
 * in it is a row ID of this shop: store views are written by code (`*` for every store view), and each condition
 * carries, beside its value, the value's label in this shop (an attribute set's name, a category's path of names),
 * which is what an import resolves by. The template is the snippet's source text, unchanged.
 *
 * @api
 */
interface SnippetExportInterface
{
    /**
     * Export one snippet
     *
     * @param int $id
     * @return SnippetExportFileInterface
     * @throws NoSuchEntityException when no snippet has the ID
     */
    public function one(int $id): SnippetExportFileInterface;

    /**
     * Export every snippet, by sort order, then ID
     *
     * @return SnippetExportFileInterface
     */
    public function all(): SnippetExportFileInterface;
}
