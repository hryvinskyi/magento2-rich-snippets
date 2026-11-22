<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * Every page behind a finding, as a CSV file to save
 *
 * @api
 */
interface FindingsCsvInterface
{
    /**
     * The file's name (`rich-snippets-{key}.csv`)
     *
     * @return string
     */
    public function getFileName(): string;

    /**
     * The file's content: a header line, then one line per page
     *
     * No cell begins with a character a spreadsheet would read as the start of a formula.
     *
     * @return string
     */
    public function getContent(): string;

    /**
     * The pages in the file
     *
     * @return int
     */
    public function getRows(): int;
}
