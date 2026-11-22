<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;

/**
 * One thing wrong with a draft
 */
class DraftProblem implements DraftProblemInterface
{
    private const PARTS = [
        self::PART_TEMPLATE,
        self::PART_NAME,
        self::PART_TYPE,
        self::PART_PAGE_KINDS,
        self::PART_CONDITIONS,
        self::PART_STORES,
        self::PART_SNIPPET,
    ];

    /**
     * @param string $part
     * @param string $path
     * @param string $code
     * @param array<string,scalar|null> $params
     * @param int|null $offset
     * @param bool $blocking
     * @throws \InvalidArgumentException when the part is unknown, the path or the code is empty, or the offset is
     *     negative
     */
    public function __construct(
        private readonly string $part,
        private readonly string $path,
        private readonly string $code,
        private readonly array $params,
        private readonly ?int $offset,
        private readonly bool $blocking
    ) {
        if (!in_array($part, self::PARTS, true)) {
            throw new \InvalidArgumentException(sprintf('A draft has no part "%s".', $part));
        }
        if ($path === '' || $code === '' || ($offset !== null && $offset < 0)) {
            throw new \InvalidArgumentException('A draft problem has a path, a code and no negative offset.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getPart(): string
    {
        return $this->part;
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
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @inheritDoc
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * @inheritDoc
     */
    public function isBlocking(): bool
    {
        return $this->blocking;
    }
}
