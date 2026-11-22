<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;

/**
 * A snippet as the editor holds it before it is saved
 *
 * It holds the values as the editor sent them; whether they make a valid snippet is the snippet rules' to say when
 * the draft is checked, previewed or saved.
 */
class DraftSnippet implements DraftSnippetInterface
{
    /**
     * @param int|null $id
     * @param string $name
     * @param string $template
     * @param bool $isBlock
     * @param bool $active
     * @param list<string> $pageKinds
     * @param string $conditions
     * @param list<int> $storeIds
     * @throws \InvalidArgumentException when the ID is not positive
     */
    public function __construct(
        private readonly ?int $id,
        private readonly string $name,
        private readonly string $template,
        private readonly bool $isBlock,
        private readonly bool $active,
        private readonly array $pageKinds,
        private readonly string $conditions,
        private readonly array $storeIds
    ) {
        if ($id !== null && $id < 1) {
            throw new \InvalidArgumentException('A draft names a stored snippet by a positive ID.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?int
    {
        return $this->id;
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
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @inheritDoc
     */
    public function isBlock(): bool
    {
        return $this->isBlock;
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @inheritDoc
     */
    public function getPageKinds(): array
    {
        return $this->pageKinds;
    }

    /**
     * @inheritDoc
     */
    public function getConditions(): string
    {
        return $this->conditions;
    }

    /**
     * @inheritDoc
     */
    public function getStoreIds(): array
    {
        return $this->storeIds;
    }
}
