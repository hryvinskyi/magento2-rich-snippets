<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;

/**
 * A question of a library template, as its definition states it
 */
class LibraryQuestion implements LibraryQuestionInterface
{
    /**
     * Codes and roots: a lower-case identifier the template language reads as a path segment
     */
    public const NAME_PATTERN = '/\A[a-z][a-z0-9_]*\z/';

    /**
     * @param string $code
     * @param string $label
     * @param string $suggester
     * @param string $root
     * @throws \InvalidArgumentException when the code or the root is not a lower-case identifier
     */
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly string $suggester,
        private readonly string $root
    ) {
        if (preg_match(self::NAME_PATTERN, $code) !== 1 || preg_match(self::NAME_PATTERN, $root) !== 1) {
            throw new \InvalidArgumentException('A question code and root are lower-case identifiers.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getSuggester(): string
    {
        return $this->suggester;
    }

    /**
     * @inheritDoc
     */
    public function getRoot(): string
    {
        return $this->root;
    }
}
