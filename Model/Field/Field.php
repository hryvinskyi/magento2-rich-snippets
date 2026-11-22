<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field;

use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;

/**
 * A field of the field picker
 */
class Field implements FieldInterface
{
    /**
     * @param string $path
     * @param string $label
     * @param string $groupCode
     * @param string $groupLabel
     * @param string|null $attributeCode
     * @param list<string> $pageKinds
     */
    public function __construct(
        private readonly string $path,
        private readonly string $label,
        private readonly string $groupCode,
        private readonly string $groupLabel,
        private readonly ?string $attributeCode = null,
        private readonly array $pageKinds = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->path;
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
    public function getGroupCode(): string
    {
        return $this->groupCode;
    }

    /**
     * @inheritDoc
     */
    public function getGroupLabel(): string
    {
        return $this->groupLabel;
    }

    /**
     * @inheritDoc
     */
    public function getAttributeCode(): ?string
    {
        return $this->attributeCode;
    }

    /**
     * @inheritDoc
     */
    public function getPageKinds(): array
    {
        return $this->pageKinds;
    }
}
