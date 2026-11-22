<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\Data\ImportEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportPreviewInterface;
use Hryvinskyi\RichSnippets\Api\SnippetImportInterface;
use Hryvinskyi\RichSnippets\Model\Idempotency\IdempotentWriteInterface;

/**
 * Previews and commits imports
 *
 * A commit reads and analyses the file afresh and checks the choices before it claims its idempotency key, outside
 * any transaction; the snippets are then written in the transaction that claims the key, so they commit together
 * or not at all, and the page cache is cleaned once, after the commit.
 *
 * @phpstan-import-type AnalysedSnippet from ImportAnalysis
 */
class SnippetImport implements SnippetImportInterface
{
    private const ACTION_COMMIT = 'snippet_import';

    /**
     * @param ImportFileReader $reader
     * @param ImportAnalysis $analysis
     * @param ImportWriter $writer
     * @param IdempotentWriteInterface $idempotentWrite
     * @param TransferFactoryInterface $results
     */
    public function __construct(
        private readonly ImportFileReader $reader,
        private readonly ImportAnalysis $analysis,
        private readonly ImportWriter $writer,
        private readonly IdempotentWriteInterface $idempotentWrite,
        private readonly TransferFactoryInterface $results
    ) {
    }

    /**
     * @inheritDoc
     */
    public function preview(string $file): ImportPreviewInterface
    {
        $read = $this->reader->read($file);
        $entries = array_map(
            fn (array $analysed): ImportEntryInterface => $this->entry($analysed),
            $this->analysis->analyse($read['snippets'])
        );

        return $this->results->preview($read['exportedAt'], $read['source'], $entries);
    }

    /**
     * @inheritDoc
     */
    public function commit(string $file, array $choices, string $idempotencyKey): array
    {
        $written = $this->idempotentWrite->once(
            $idempotencyKey,
            self::ACTION_COMMIT,
            function () use ($file, $choices): callable {
                $chosen = $this->writer->chosen(
                    $this->analysis->analyse($this->reader->read($file)['snippets']),
                    $choices
                );

                return fn (): array => $this->writer->write($chosen);
            }
        );
        $imported = [];
        foreach ($written as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = $record[ImportWriter::ID] ?? null;
            $code = $record[ImportWriter::CODE] ?? null;
            $name = $record[ImportWriter::NAME] ?? null;
            $choice = ImportChoice::tryFrom(is_string($record[ImportWriter::CHOICE] ?? null)
                ? $record[ImportWriter::CHOICE]
                : '');
            if (!is_int($id) || !is_string($code) || !is_string($name) || $choice === null) {
                continue;
            }
            $imported[] = $this->results->imported($id, $code, $name, $choice);
        }

        return $imported;
    }

    /**
     * An analysed snippet as the preview gives it
     *
     * @param AnalysedSnippet $analysed
     * @return ImportEntryInterface
     */
    private function entry(array $analysed): ImportEntryInterface
    {
        $entry = $analysed['entry'];
        $existing = $analysed['existing'];

        return $this->results->entry(
            $entry['code'],
            $entry['name'],
            $analysed['checked']['type'],
            $entry['block'],
            $analysed['status'],
            $existing?->getId(),
            $existing?->getUpdatedAt(),
            $entry['updatedAt'],
            $analysed['choices'],
            $analysed['checked']['problems'],
            $entry['block'] ? [] : $entry['pageKinds'],
            $analysed['summary']
        );
    }
}
