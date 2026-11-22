<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Data\SnippetExportFileInterface;

/**
 * An export's file name and document
 */
class ExportFile implements SnippetExportFileInterface
{
    /**
     * @param string $fileName
     * @param array<string,mixed> $document
     */
    public function __construct(
        private readonly string $fileName,
        private readonly array $document
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
    public function getDocument(): array
    {
        return $this->document;
    }
}
