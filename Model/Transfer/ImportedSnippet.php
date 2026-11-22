<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\Data\ImportedSnippetInterface;

/**
 * A snippet an import wrote
 */
class ImportedSnippet implements ImportedSnippetInterface
{
    /**
     * @param int $id
     * @param string $code
     * @param string $name
     * @param ImportChoice $choice
     */
    public function __construct(
        private readonly int $id,
        private readonly string $code,
        private readonly string $name,
        private readonly ImportChoice $choice
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
    public function getChoice(): ImportChoice
    {
        return $this->choice;
    }
}
