<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\ConditionSentenceInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreePrinterInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemReason;
use Hryvinskyi\RichSnippets\Api\Data\ImportStatus;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeEdgesInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeGraph;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Finds how each snippet of an import file stands against the shop, what can be done with it and what is wrong
 *
 * A snippet is matched with the shop's by code. One that breaks a rule is invalid. So is one that would include
 * itself once every valid snippet of the file is in the shop under its code, with the shop's other snippets as they
 * are. One whose replacing would change nothing the replace writes (name, building block or not, template, kinds of
 * page, conditions, origin) is identical: the replace keeps the switch, the store views and the place in the list,
 * so they do not count. A snippet with a condition this shop cannot resolve can be neither replaced nor added; one
 * left with no store view of this shop cannot be added. An include of a code neither the shop nor the file has is
 * reported, and imports all the same: it writes nothing until such a snippet exists.
 *
 * @phpstan-import-type FileSnippet from ImportFileReader
 * @phpstan-import-type CheckedSnippet from ImportCheck
 * @phpstan-type AnalysedSnippet array{entry: FileSnippet, checked: CheckedSnippet, existing: SnippetInterface|null,
 *     status: ImportStatus, choices: list<ImportChoice>, summary: string}
 */
class ImportAnalysis
{
    private const LOOP_SEPARATOR = ' → ';

    /**
     * @param ImportCheck $check
     * @param SnippetRepositoryInterface $snippets
     * @param IncludeEdgesInterface $edges
     * @param IncludeGraph $graph
     * @param ConditionSentenceInterface $sentence
     * @param ConditionTreePrinterInterface $printer
     * @param StoreManagerInterface $storeManager
     * @param TransferFactoryInterface $results
     */
    public function __construct(
        private readonly ImportCheck $check,
        private readonly SnippetRepositoryInterface $snippets,
        private readonly IncludeEdgesInterface $edges,
        private readonly IncludeGraph $graph,
        private readonly ConditionSentenceInterface $sentence,
        private readonly ConditionTreePrinterInterface $printer,
        private readonly StoreManagerInterface $storeManager,
        private readonly TransferFactoryInterface $results
    ) {
    }

    /**
     * Analyse the snippets of a file, in the file's order
     *
     * @param list<FileSnippet> $entries
     * @return list<AnalysedSnippet>
     */
    public function analyse(array $entries): array
    {
        $existing = [];
        foreach ($this->snippets->list() as $snippet) {
            $existing[$snippet->getCode()] = $snippet;
        }
        $checks = [];
        $edges = $this->edges->edges();
        foreach ($entries as $entry) {
            $checked = $this->check->check($entry);
            $checks[] = $checked;
            if (!$checked['invalid']) {
                $edges[$entry['code']] = $checked['includes'];
            }
        }
        $known = array_flip(array_merge(array_keys($existing), array_column($entries, 'code')));
        $labelStore = (int) $this->storeManager->getDefaultStoreView()?->getId();

        $analysed = [];
        foreach ($entries as $index => $entry) {
            $checked = $this->withIncludeProblems($entry['code'], $checks[$index], $edges, $known);
            $stored = $existing[$entry['code']] ?? null;
            $status = $this->status($entry, $checked, $stored);
            $analysed[] = [
                'entry' => $entry,
                'checked' => $checked,
                'existing' => $stored,
                'status' => $status,
                'choices' => $this->choices($status, $checked),
                'summary' => $checked['unresolved'] ? '' : $this->sentence->of($checked['conditions'], $labelStore),
            ];
        }

        return $analysed;
    }

    /**
     * The check with the loop the snippet would close and the includes nothing has
     *
     * @param string $code
     * @param CheckedSnippet $checked
     * @param array<string,list<string>> $edges the shop's includes with the file's valid snippets put in
     * @param array<array-key,int> $known the codes of the shop and of the file
     * @return CheckedSnippet
     */
    private function withIncludeProblems(string $code, array $checked, array $edges, array $known): array
    {
        if ($checked['invalid']) {
            return $checked;
        }
        foreach ($checked['includes'] as $include) {
            if (!isset($known[$include])) {
                $checked['problems'][] = $this->results->problem(
                    ImportProblemReason::IncludeMissing,
                    ['code' => $include]
                );
            }
        }
        $loop = $this->graph->loopThrough($code, $checked['includes'], $edges);
        if ($loop !== []) {
            $checked['problems'][] = $this->results->problem(
                ImportProblemReason::IncludeLoop,
                ['codes' => implode(self::LOOP_SEPARATOR, $loop)]
            );
            $checked['invalid'] = true;
        }

        return $checked;
    }

    /**
     * How a snippet stands against the shop's one with its code
     *
     * @param FileSnippet $entry
     * @param CheckedSnippet $checked
     * @param SnippetInterface|null $stored
     * @return ImportStatus
     */
    private function status(array $entry, array $checked, ?SnippetInterface $stored): ImportStatus
    {
        if ($checked['invalid']) {
            return ImportStatus::Invalid;
        }
        if ($stored === null) {
            return ImportStatus::New;
        }

        return !$checked['unresolved'] && $this->isSame($entry, $checked, $stored)
            ? ImportStatus::Identical
            : ImportStatus::Changed;
    }

    /**
     * What can be done with a snippet, the default first
     *
     * @param ImportStatus $status
     * @param CheckedSnippet $checked
     * @return list<ImportChoice>
     */
    private function choices(ImportStatus $status, array $checked): array
    {
        if ($status === ImportStatus::Invalid || $status === ImportStatus::Identical) {
            return [];
        }
        $addable = !$checked['unresolved'] && !$checked['storeless'];
        if ($status === ImportStatus::New) {
            return $addable ? [ImportChoice::Add, ImportChoice::Skip] : [ImportChoice::Skip];
        }
        $choices = [];
        if (!$checked['unresolved']) {
            $choices[] = ImportChoice::Replace;
        }
        if ($addable) {
            $choices[] = ImportChoice::KeepBoth;
        }
        $choices[] = ImportChoice::Skip;

        return $choices;
    }

    /**
     * Whether replacing the shop's snippet with the file's would change nothing
     *
     * @param FileSnippet $entry
     * @param CheckedSnippet $checked
     * @param SnippetInterface $stored
     * @return bool
     */
    private function isSame(array $entry, array $checked, SnippetInterface $stored): bool
    {
        return trim($entry['name']) === $stored->getName()
            && $entry['block'] === $stored->isBlock()
            && $this->sameTemplate($entry['template'], $stored->getTemplate())
            && $this->sorted($entry['block'] ? [] : $entry['pageKinds']) === $this->sorted($stored->getPageKinds())
            && $this->printer->print($checked['conditions']) === $this->printer->print($stored->getConditions())
            && $this->originKey($checked['origin']) === $this->originKey($stored->getOrigin());
    }

    /**
     * Whether two templates are the same JSON, however they are laid out
     *
     * @param string $first
     * @param string $second
     * @return bool
     */
    private function sameTemplate(string $first, string $second): bool
    {
        return $first === $second || json_decode($first, true) === json_decode($second, true);
    }

    /**
     * Distinct values in order
     *
     * @param list<string> $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * An origin as a comparable key
     *
     * @param OriginInterface|null $origin
     * @return string
     */
    private function originKey(?OriginInterface $origin): string
    {
        return $origin === null ? '' : $origin->getCode() . '@' . $origin->getVersion();
    }
}
