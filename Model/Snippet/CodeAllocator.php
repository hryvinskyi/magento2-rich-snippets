<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\CodeAllocatorInterface;
use Magento\Framework\Filter\TranslitUrl;

/**
 * Allocates a snippet code from a name: transliterated as the platform writes URL keys, then the first free code of
 * its sequence among the stored codes
 */
class CodeAllocator implements CodeAllocatorInterface
{
    /**
     * @param TranslitUrl $slugs
     * @param CodeSequence $sequence
     * @param SnippetReaderInterface $snippets
     */
    public function __construct(
        private readonly TranslitUrl $slugs,
        private readonly CodeSequence $sequence,
        private readonly SnippetReaderInterface $snippets
    ) {
    }

    /**
     * @inheritDoc
     */
    public function allocate(string $name): string
    {
        $base = $this->sequence->baseOf($this->slugs->filter($name));

        return $this->sequence->firstFree(
            $base,
            $this->snippets->codesStartingWith($this->sequence->prefixOf($base))
        );
    }
}
