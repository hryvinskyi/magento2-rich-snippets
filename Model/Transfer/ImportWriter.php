<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\Exception\ImportReason;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetImportException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeEdgesInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeGraph;
use Hryvinskyi\RichSnippets\Model\Transfer\Write\ImportChoiceWriterInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Checks the choices made for an import file and writes the snippets as chosen
 *
 * A choice must name a snippet of the file and be one that snippet is offered; a snippet without a choice is
 * skipped. The snippets chosen to be replaced or added, under the file's code, must not include themselves through
 * each other or the shop's other snippets; one kept beside the shop's gets a new code nothing includes yet, so it
 * closes no loop. Each choice is written by the writer registered for it.
 *
 * @phpstan-import-type AnalysedSnippet from ImportAnalysis
 */
class ImportWriter
{
    public const ID = 'id';
    public const CODE = 'code';
    public const NAME = 'name';
    public const CHOICE = 'choice';

    private const LOOP_SEPARATOR = ' → ';
    private const UNDER_FILE_CODE = [ImportChoice::Replace, ImportChoice::Add];

    /**
     * @param IncludeEdgesInterface $edges
     * @param IncludeGraph $graph
     * @param array<string,ImportChoiceWriterInterface> $writers by choice value
     */
    public function __construct(
        private readonly IncludeEdgesInterface $edges,
        private readonly IncludeGraph $graph,
        private readonly array $writers = []
    ) {
    }

    /**
     * The choice of each analysed snippet, refused when one is not offered or the choices would close a loop
     *
     * @param list<AnalysedSnippet> $analysed
     * @param array<string,ImportChoice> $choices by code
     * @return list<array{AnalysedSnippet, ImportChoice}> in the file's order
     * @throws SnippetImportException
     */
    public function chosen(array $analysed, array $choices): array
    {
        $codes = [];
        foreach ($analysed as $snippet) {
            $codes[$snippet['entry']['code']] = true;
        }
        foreach (array_keys($choices) as $code) {
            if (!isset($codes[(string) $code])) {
                throw new SnippetImportException(ImportReason::ChoiceUnknown, ['code' => (string) $code]);
            }
        }
        $chosen = [];
        $edges = $this->edges->edges();
        foreach ($analysed as $snippet) {
            $code = $snippet['entry']['code'];
            $choice = $choices[$code] ?? ImportChoice::Skip;
            if ($choice !== ImportChoice::Skip && !in_array($choice, $snippet['choices'], true)) {
                throw new SnippetImportException(
                    ImportReason::ChoiceNotOffered,
                    ['code' => $code, 'choice' => $choice->value]
                );
            }
            if (in_array($choice, self::UNDER_FILE_CODE, true)) {
                $edges[$code] = $snippet['checked']['includes'];
            }
            $chosen[] = [$snippet, $choice];
        }
        foreach ($chosen as [$snippet, $choice]) {
            $this->assertNoLoop($snippet, $choice, $edges);
        }

        return $chosen;
    }

    /**
     * Write the chosen snippets, in order
     *
     * @param list<array{AnalysedSnippet,ImportChoice}> $chosen
     * @return list<array{id: int, code: string, name: string, choice: string}> the snippets written
     * @throws SnippetRuleException|CompileException|NoSuchEntityException
     */
    public function write(array $chosen): array
    {
        $written = [];
        foreach ($chosen as [$snippet, $choice]) {
            $writer = $this->writers[$choice->value]
                ?? throw new \LogicException(sprintf('No import writer is registered for "%s".', $choice->value));
            $stored = $writer->write($snippet);
            if ($stored === null) {
                continue;
            }
            $written[] = [
                self::ID => (int) $stored->getId(),
                self::CODE => $stored->getCode(),
                self::NAME => $stored->getName(),
                self::CHOICE => $choice->value,
            ];
        }

        return $written;
    }

    /**
     * Refuse a snippet written under the file's code that would include itself
     *
     * @param AnalysedSnippet $snippet
     * @param ImportChoice $choice
     * @param array<string,list<string>> $edges
     * @return void
     * @throws SnippetImportException
     */
    private function assertNoLoop(array $snippet, ImportChoice $choice, array $edges): void
    {
        if (!in_array($choice, self::UNDER_FILE_CODE, true)) {
            return;
        }
        $code = $snippet['entry']['code'];
        $loop = $this->graph->loopThrough($code, $snippet['checked']['includes'], $edges);
        if ($loop !== []) {
            throw new SnippetImportException(
                ImportReason::IncludeLoop,
                ['codes' => implode(self::LOOP_SEPARATOR, $loop)]
            );
        }
    }
}
