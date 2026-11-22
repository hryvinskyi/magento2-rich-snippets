<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Factory;

use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\Data\ImportedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportedSnippetInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\ImportEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportEntryInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\ImportPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportPreviewInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemReason;
use Hryvinskyi\RichSnippets\Api\Data\ImportStatus;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExportFileInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExportFileInterfaceFactory;
use Hryvinskyi\RichSnippets\Model\Transfer\TransferFactoryInterface;

/**
 * Creates what export and import answer through the factories Magento generates for their interfaces
 */
class TransferFactoryAdapter implements TransferFactoryInterface
{
    /**
     * @param SnippetExportFileInterfaceFactory $files
     * @param ImportProblemInterfaceFactory $problems
     * @param ImportEntryInterfaceFactory $entries
     * @param ImportPreviewInterfaceFactory $previews
     * @param ImportedSnippetInterfaceFactory $imported
     */
    public function __construct(
        private readonly SnippetExportFileInterfaceFactory $files,
        private readonly ImportProblemInterfaceFactory $problems,
        private readonly ImportEntryInterfaceFactory $entries,
        private readonly ImportPreviewInterfaceFactory $previews,
        private readonly ImportedSnippetInterfaceFactory $imported
    ) {
    }

    /**
     * @inheritDoc
     */
    public function exportFile(string $fileName, array $document): SnippetExportFileInterface
    {
        return $this->files->create(['fileName' => $fileName, 'document' => $document]);
    }

    /**
     * @inheritDoc
     */
    public function problem(ImportProblemReason $reason, array $params): ImportProblemInterface
    {
        return $this->problems->create(['reason' => $reason, 'params' => $params]);
    }

    /**
     * @inheritDoc
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
    ): ImportEntryInterface {
        return $this->entries->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'block' => $block,
            'status' => $status,
            'existingId' => $existingId,
            'changedHere' => $changedHere,
            'changedInFile' => $changedInFile,
            'choices' => $choices,
            'problems' => $problems,
            'pageKinds' => $pageKinds,
            'conditionSummary' => $conditionSummary,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function preview(string $exportedAt, string $source, array $entries): ImportPreviewInterface
    {
        return $this->previews->create(['exportedAt' => $exportedAt, 'source' => $source, 'entries' => $entries]);
    }

    /**
     * @inheritDoc
     */
    public function imported(int $id, string $code, string $name, ImportChoice $choice): ImportedSnippetInterface
    {
        return $this->imported->create(['id' => $id, 'code' => $code, 'name' => $name, 'choice' => $choice]);
    }
}
