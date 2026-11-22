<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A snippet an import wrote: replaced, added beside the shop's one, or added
 *
 * @api
 */
interface ImportedSnippetInterface
{
    /**
     * The stored snippet's ID
     *
     * @return int
     */
    public function getId(): int;

    /**
     * The stored snippet's code; for one kept beside the shop's, a code of its own
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The stored snippet's name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * What the import did with it
     *
     * @return ImportChoice
     */
    public function getChoice(): ImportChoice;
}
