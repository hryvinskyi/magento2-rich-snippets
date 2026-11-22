<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Data\ImportEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportPreviewInterface;

/**
 * What importing a file would do
 */
class ImportPreview implements ImportPreviewInterface
{
    /**
     * @param string $exportedAt
     * @param string $source
     * @param list<ImportEntryInterface> $entries
     */
    public function __construct(
        private readonly string $exportedAt,
        private readonly string $source,
        private readonly array $entries
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getExportedAt(): string
    {
        return $this->exportedAt;
    }

    /**
     * @inheritDoc
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @inheritDoc
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}
