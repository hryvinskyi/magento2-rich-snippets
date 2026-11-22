<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FindingsCsvInterface;

/**
 * The pages behind a finding as a CSV file
 */
class FindingsCsv implements FindingsCsvInterface
{
    /**
     * @param string $fileName
     * @param string $content
     * @param int $rows
     */
    public function __construct(
        private readonly string $fileName,
        private readonly string $content,
        private readonly int $rows
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @inheritDoc
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @inheritDoc
     */
    public function getRows(): int
    {
        return $this->rows;
    }
}
