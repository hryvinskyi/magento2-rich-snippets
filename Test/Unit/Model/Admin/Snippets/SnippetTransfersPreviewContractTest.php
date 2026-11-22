<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Snippets;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemReason;
use Hryvinskyi\RichSnippets\Api\Data\ImportStatus;
use Hryvinskyi\RichSnippets\Api\Exception\ImportReason;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetImportException;
use Hryvinskyi\RichSnippets\Api\SnippetExportInterface;
use Hryvinskyi\RichSnippets\Api\SnippetImportInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsListing;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsRequest;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetTransfers;
use Hryvinskyi\RichSnippets\Model\Transfer\ImportEntry;
use Hryvinskyi\RichSnippets\Model\Transfer\ImportPreview;
use Hryvinskyi\RichSnippets\Model\Transfer\ImportProblem;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * The import preview is answered exactly as the data contract (`snippets-import-preview.json`) writes it, which the
 * Snippets page's browser tests read as the server's answer: the file's export time and shop, the count, and each
 * snippet with its standing, choices, problems, kinds of page and conditions; a file refused as a whole answers its
 * reason
 */
class SnippetTransfersPreviewContractTest extends TestCase
{
    private const CONTRACT = __DIR__ . '/../../../../_contract/admin-pages/snippets-import-preview.json';
    private const FILE = '{"format": "rich-snippets"}';

    public function testThePreviewIsAnsweredAsTheContractWritesIt(): void
    {
        $contract = $this->contract();
        $entries = [];
        foreach ($this->list($contract['snippets'] ?? null) as $snippet) {
            $entries[] = $this->entry($snippet);
        }
        $imports = $this->createMock(SnippetImportInterface::class);
        $imports->expects(self::once())->method('preview')->with(self::FILE)->willReturn(new ImportPreview(
            $this->text($contract['exportedAt'] ?? null),
            $this->text($contract['source'] ?? null),
            $entries
        ));

        self::assertSame($contract, $this->transfers($imports)->preview(['file' => self::FILE]));
    }

    public function testAFileRefusedAsAWholeAnswersItsReason(): void
    {
        $imports = $this->createMock(SnippetImportInterface::class);
        $imports->method('preview')->willThrowException(new SnippetImportException(ImportReason::NotAnExport));

        try {
            $this->transfers($imports)->preview(['file' => self::FILE]);
            self::fail('A refused file is answered as a refused request.');
        } catch (InvalidWizardRequestException $e) {
            self::assertSame(ImportReason::NotAnExport->value, $e->getReason());
        }
    }

    /**
     * The transfers over an import, as the container builds them
     *
     * @param SnippetImportInterface $imports
     * @return SnippetTransfers
     */
    private function transfers(SnippetImportInterface $imports): SnippetTransfers
    {
        return new SnippetTransfers(
            new SnippetsRequest($this->createMock(StoreManagerInterface::class)),
            $this->createMock(SnippetExportInterface::class),
            $imports,
            $this->createMock(SnippetsListing::class)
        );
    }

    /**
     * One snippet of the file, built from the contract's values
     *
     * @param array<mixed> $snippet
     * @return ImportEntry
     */
    private function entry(array $snippet): ImportEntry
    {
        $problems = [];
        foreach ($this->list($snippet['problems'] ?? null) as $problem) {
            $params = $problem['params'] ?? null;
            self::assertIsArray($params);
            $texts = [];
            foreach ($params as $name => $value) {
                $texts[(string) $name] = $this->text($value);
            }
            $problems[] = new ImportProblem(ImportProblemReason::from($this->text($problem['reason'] ?? null)), $texts);
        }
        $existingId = $snippet['existingId'] ?? null;
        $changedHere = $snippet['changedHere'] ?? null;
        $changedInFile = $snippet['changedInFile'] ?? null;

        return new ImportEntry(
            $this->text($snippet['code'] ?? null),
            $this->text($snippet['name'] ?? null),
            $this->text($snippet['type'] ?? null),
            ($snippet['isBlock'] ?? null) === true,
            ImportStatus::from($this->text($snippet['status'] ?? null)),
            is_int($existingId) ? $existingId : null,
            is_string($changedHere) ? $changedHere : null,
            is_string($changedInFile) ? $changedInFile : null,
            array_map(
                fn (mixed $choice): ImportChoice => ImportChoice::from($this->text($choice)),
                $this->values($snippet['choices'] ?? null)
            ),
            $problems,
            array_map(fn (mixed $kind): string => $this->text($kind), $this->values($snippet['pageKinds'] ?? null)),
            $this->text($snippet['conditionSummary'] ?? null)
        );
    }

    /**
     * The contract's preview
     *
     * @return array<mixed>
     */
    private function contract(): array
    {
        $decoded = json_decode((string) file_get_contents(self::CONTRACT), true, 512, JSON_THROW_ON_ERROR);
        $preview = is_array($decoded) ? ($decoded['preview'] ?? null) : null;
        self::assertIsArray($preview);

        return $preview;
    }

    /**
     * @param mixed $value
     * @return list<array<mixed>>
     */
    private function list(mixed $value): array
    {
        $list = [];
        foreach ($this->values($value) as $item) {
            self::assertIsArray($item);
            $list[] = $item;
        }

        return $list;
    }

    /**
     * @param mixed $value
     * @return list<mixed>
     */
    private function values(mixed $value): array
    {
        self::assertIsArray($value);

        return array_values($value);
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function text(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
