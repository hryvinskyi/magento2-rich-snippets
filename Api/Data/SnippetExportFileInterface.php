<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * An export: the file name it is offered under and the document it holds
 *
 * @api
 */
interface SnippetExportFileInterface
{
    /**
     * The file name: `{name}.json` for one snippet, `rich-snippets-{shop}.json` for all, each part as a slug
     *
     * @return string
     */
    public function getFileName(): string;

    /**
     * The export document, as SnippetExportInterface describes it
     *
     * @return array<string,mixed>
     */
    public function getDocument(): array;
}
