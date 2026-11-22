<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;

/**
 * One answer to a library question
 */
class AnswerOption implements AnswerOptionInterface
{
    /**
     * @param string $key
     * @param string $label
     * @param bool $leftOut
     * @param mixed $fragment null when the answer leaves the property out
     * @param string $hint
     * @param HintTone $tone
     * @param int|null $fillCount
     * @throws \InvalidArgumentException when the key is empty, or an answer that leaves the property out has a
     *     fragment
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly bool $leftOut,
        private readonly mixed $fragment,
        private readonly string $hint,
        private readonly HintTone $tone,
        private readonly ?int $fillCount = null
    ) {
        if ($key === '') {
            throw new \InvalidArgumentException('An answer has a key.');
        }
        if ($leftOut && $fragment !== null) {
            throw new \InvalidArgumentException('An answer that leaves the property out has no fragment.');
        }
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
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function isLeftOut(): bool
    {
        return $this->leftOut;
    }

    /**
     * @inheritDoc
     */
    public function getFragment(): mixed
    {
        return $this->fragment;
    }

    /**
     * @inheritDoc
     */
    public function getHint(): string
    {
        return $this->hint;
    }

    /**
     * @inheritDoc
     */
    public function getTone(): HintTone
    {
        return $this->tone;
    }

    /**
     * @inheritDoc
     */
    public function getFillCount(): ?int
    {
        return $this->fillCount;
    }
}
