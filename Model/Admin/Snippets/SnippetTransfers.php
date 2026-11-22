<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\Data\ImportedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExportFileInterface;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetImportException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\SnippetExportInterface;
use Hryvinskyi\RichSnippets\Api\SnippetImportInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Runs the Snippets page's export and import and answers them
 *
 * A preview answers `{"exportedAt", "source", "count", "snippets": [{"code", "name", "type", "isBlock", "status",
 * "existingId", "changedHere", "changedInFile", "choices", "problems": [{"reason", "params"}], "pageKinds",
 * "conditionSummary"}]}`. A commit answers the list after it, for the store view the body names (see
 * SnippetRowsPresenter), with `"imported": [{"id", "code", "name", "choice"}]` and its `"count"`. A file refused as a
 * whole answers 400 with the import's reason (`not_an_export`, `file_too_large` …), as does a choice that is not
 * offered, a loop the choices close, an idempotency key that is refused, and a snippet the shop's rules refuse since
 * the preview (`rule_broken`, `template_invalid`). An export of a snippet that is not stored answers 400
 * `unknown_snippet`.
 *
 * @phpstan-type Entry array{code: string, name: string, type: string, isBlock: bool, status: string,
 *     existingId: int|null, changedHere: string|null, changedInFile: string|null, choices: list<string>,
 *     problems: list<array{reason: string, params: array<string, string>}>, pageKinds: list<string>,
 *     conditionSummary: string}
 * @phpstan-type Preview array{exportedAt: string, source: string, count: int, snippets: list<Entry>}
 */
class SnippetTransfers
{
    public const RULE_BROKEN = 'rule_broken';
    public const TEMPLATE_INVALID = 'template_invalid';

    /**
     * @param SnippetsRequest $request
     * @param SnippetExportInterface $exports
     * @param SnippetImportInterface $imports
     * @param SnippetsListing $listing
     */
    public function __construct(
        private readonly SnippetsRequest $request,
        private readonly SnippetExportInterface $exports,
        private readonly SnippetImportInterface $imports,
        private readonly SnippetsListing $listing
    ) {
    }

    /**
     * The export the query asks for: `?id=<id>` for one snippet, none for all
     *
     * @param array<mixed> $params
     * @return SnippetExportFileInterface
     * @throws InvalidWizardRequestException
     */
    public function export(array $params): SnippetExportFileInterface
    {
        $id = $this->request->exportId($params);
        if ($id === null) {
            return $this->exports->all();
        }
        try {
            return $this->exports->one($id);
        } catch (NoSuchEntityException $e) {
            throw new InvalidWizardRequestException(SnippetWrites::UNKNOWN_SNIPPET, 'No snippet has the ID.', $e);
        }
    }

    /**
     * What importing the file in the body would do: `{"file"}`
     *
     * @param array<mixed> $body
     * @return Preview
     * @throws InvalidWizardRequestException
     */
    public function preview(array $body): array
    {
        $file = $this->request->file($body);
        try {
            $preview = $this->imports->preview($file);
        } catch (SnippetImportException $e) {
            throw new InvalidWizardRequestException($e->getReason()->value, 'The import file is refused.', $e);
        }
        $entries = array_map(fn (ImportEntryInterface $entry): array => $this->entry($entry), $preview->getEntries());

        return [
            'exportedAt' => $preview->getExportedAt(),
            'source' => $preview->getSource(),
            'count' => count($entries),
            'snippets' => $entries,
        ];
    }

    /**
     * Import the file in the body as chosen: `{"file", "choices": {code: choice}, "idempotencyKey", "store"}`
     *
     * @param array<mixed> $body
     * @return array<string, mixed>
     * @throws InvalidWizardRequestException
     */
    public function commit(array $body): array
    {
        $file = $this->request->file($body);
        $choices = $this->request->choices($body);
        $key = $this->request->idempotencyKey($body);
        $this->request->storeId($body);
        try {
            $imported = $this->imports->commit($file, $choices, $key);
        } catch (SnippetImportException $e) {
            throw new InvalidWizardRequestException($e->getReason()->value, 'The import is refused.', $e);
        } catch (IdempotencyException $e) {
            throw new InvalidWizardRequestException($e->getReason()->value, 'The idempotency key is refused.', $e);
        } catch (SnippetRuleException $e) {
            throw new InvalidWizardRequestException(self::RULE_BROKEN, 'A snippet of the file is refused.', $e);
        } catch (CompileException $e) {
            throw new InvalidWizardRequestException(self::TEMPLATE_INVALID, 'A template does not compile.', $e);
        }
        $records = array_map(
            static fn (ImportedSnippetInterface $snippet): array => [
                'id' => $snippet->getId(),
                'code' => $snippet->getCode(),
                'name' => $snippet->getName(),
                'choice' => $snippet->getChoice()->value,
            ],
            $imported
        );

        return $this->listing->of($body) + ['imported' => $records, 'count' => count($records)];
    }

    /**
     * One snippet of the preview
     *
     * @param ImportEntryInterface $entry
     * @return Entry
     */
    private function entry(ImportEntryInterface $entry): array
    {
        return [
            'code' => $entry->getCode(),
            'name' => $entry->getName(),
            'type' => $entry->getType(),
            'isBlock' => $entry->isBlock(),
            'status' => $entry->getStatus()->value,
            'existingId' => $entry->getExistingId(),
            'changedHere' => $entry->getChangedHere(),
            'changedInFile' => $entry->getChangedInFile(),
            'choices' => array_map(static fn (ImportChoice $choice): string => $choice->value, $entry->getChoices()),
            'problems' => array_map(
                static fn (ImportProblemInterface $problem): array => [
                    'reason' => $problem->getReason()->value,
                    'params' => $problem->getParams(),
                ],
                $entry->getProblems()
            ),
            'pageKinds' => $entry->getPageKinds(),
            'conditionSummary' => $entry->getConditionSummary(),
        ];
    }
}
