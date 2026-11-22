<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionTargetInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionTone;

/**
 * One thing on the Overview that wants fixing
 */
class AttentionItem implements AttentionItemInterface
{
    /**
     * @param string $key
     * @param AttentionTone $tone
     * @param string $title
     * @param list<string> $titleArguments
     * @param string $description
     * @param list<string> $descriptionArguments
     * @param string $actionLabel
     * @param AttentionTargetInterface $target
     */
    public function __construct(
        private readonly string $key,
        private readonly AttentionTone $tone,
        private readonly string $title,
        private readonly array $titleArguments,
        private readonly string $description,
        private readonly array $descriptionArguments,
        private readonly string $actionLabel,
        private readonly AttentionTargetInterface $target
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function getTone(): AttentionTone
    {
        return $this->tone;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @inheritDoc
     */
    public function getTitleArguments(): array
    {
        return $this->titleArguments;
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @inheritDoc
     */
    public function getDescriptionArguments(): array
    {
        return $this->descriptionArguments;
    }

    /**
     * @inheritDoc
     */
    public function getActionLabel(): string
    {
        return $this->actionLabel;
    }

    /**
     * @inheritDoc
     */
    public function getTarget(): AttentionTargetInterface
    {
        return $this->target;
    }
}
