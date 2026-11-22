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
use Hryvinskyi\RichSnippets\Api\Data\ImportEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemReason;
use Hryvinskyi\RichSnippets\Api\Data\ImportStatus;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExportFileInterface;

/**
 * Creates what export and import answer
 */
interface TransferFactoryInterface
{
    /**
     * An export
     *
     * @param string $fileName
     * @param array<string,mixed> $document
     * @return SnippetExportFileInterface
     */
    public function exportFile(string $fileName, array $document): SnippetExportFileInterface;

    /**
     * A problem with a snippet of an import file
     *
     * @param ImportProblemReason $reason
     * @param array<string,string> $params
     * @return ImportProblemInterface
     */
    public function problem(ImportProblemReason $reason, array $params): ImportProblemInterface;

    /**
     * A snippet of an import file as the import found it
     *
     * @param string $code
     * @param string $name
     * @param string $type
     * @param bool $block
     * @param ImportStatus $status
     * @param int|null $existingId
     * @param string|null $changedHere
     * @param string|null $changedInFile
     * @param list<ImportChoice> $choices
     * @param list<ImportProblemInterface> $problems
     * @param list<string> $pageKinds
     * @param string $conditionSummary
     * @return ImportEntryInterface
     */
    public function entry(
        string $code,
        string $name,
        string $type,
        bool $block,
        ImportStatus $status,
        ?int $existingId,
        ?string $changedHere,
        ?string $changedInFile,
        array $choices,
        array $problems,
        array $pageKinds,
        string $conditionSummary
    ): ImportEntryInterface;

    /**
     * What importing a file would do
     *
     * @param string $exportedAt
     * @param string $source
     * @param list<ImportEntryInterface> $entries
     * @return ImportPreviewInterface
     */
    public function preview(string $exportedAt, string $source, array $entries): ImportPreviewInterface;

    /**
     * A snippet an import wrote
     *
     * @param int $id
     * @param string $code
     * @param string $name
     * @param ImportChoice $choice
     * @return ImportedSnippetInterface
     */
    public function imported(int $id, string $code, string $name, ImportChoice $choice): ImportedSnippetInterface;
}
