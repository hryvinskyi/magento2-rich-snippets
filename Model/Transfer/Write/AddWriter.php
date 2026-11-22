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
 * Adds a snippet the shop has none of, under the file's code, so the includes that name it find it, switched off
 */
class AddWriter implements ImportChoiceWriterInterface
{
    /**
     * @param ImportedCopy $copies
     */
    public function __construct(private readonly ImportedCopy $copies)
    {
    }

    /**
     * @inheritDoc
     */
    public function write(array $analysed): ?SnippetInterface
    {
        $entry = $analysed['entry'];

        return $this->copies->save($analysed, $entry['code'], $this->copies->freeName($entry['name']));
    }
}
