<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Write;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * Leaves the shop as it is
 */
class SkipWriter implements ImportChoiceWriterInterface
{
    /**
     * @inheritDoc
     */
    public function write(array $analysed): ?SnippetInterface
    {
        return null;
    }
}
