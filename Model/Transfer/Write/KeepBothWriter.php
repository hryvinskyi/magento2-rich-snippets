<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Write;

use Hryvinskyi\RichSnippets\Api\CodeAllocatorInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * Adds the file's snippet beside the shop's one with its code: named "{name} (imported)", with a code of its own
 * from the code allocator, switched off
 *
 * Includes that name the code keep finding the shop's snippet, not the one added.
 */
class KeepBothWriter implements ImportChoiceWriterInterface
{
    /**
     * @param ImportedCopy $copies
     * @param CodeAllocatorInterface $codes
     */
    public function __construct(
        private readonly ImportedCopy $copies,
        private readonly CodeAllocatorInterface $codes
    ) {
    }

    /**
     * @inheritDoc
     */
    public function write(array $analysed): ?SnippetInterface
    {
        $name = $this->copies->freeName((string) __('%1 (imported)', trim($analysed['entry']['name'])));

        return $this->copies->save($analysed, $this->codes->allocate($name), $name);
    }
}
