<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;

/**
 * A snippet an add from the library ended with
 */
class AddedSnippet implements AddedSnippetInterface
{
    /**
     * @param int $id
     * @param string $code
     * @param string $name
     * @param bool $block
     * @param string $template
     * @param bool $reused
     */
    public function __construct(
        private readonly int $id,
        private readonly string $code,
        private readonly string $name,
        private readonly bool $block,
        private readonly string $template,
        private readonly bool $reused
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getId(): int
    {
        return $this->id;
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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function isBlock(): bool
    {
        return $this->block;
    }

    /**
     * @inheritDoc
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @inheritDoc
     */
    public function isReused(): bool
    {
        return $this->reused;
    }
}
