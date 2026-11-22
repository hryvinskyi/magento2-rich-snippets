<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check;

use Hryvinskyi\RichSnippets\Api\Data\FindingItemInterface;

/**
 * Writes the pages behind a finding as CSV: a header line, then one line per page
 *
 * A cell that begins with `=`, `+`, `-`, `@`, a tab or a carriage return would be read as a formula by a
 * spreadsheet, so it gets a `'` in front: a product named "=HYPERLINK(…)" stays a name. A cell is quoted as RFC 4180
 * quotes it when it holds a separator, a quote, white space or a line break.
 */
class FindingsCsvContent
{
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];
    private const QUOTE = "'";
    private const ENCLOSURE = '"';
    private const NEEDS_QUOTES = ",\" \t\r\n";

    /**
     * The CSV text
     *
     * @param iterable<list<FindingItemInterface>> $batches the pages, a batch at a time
     * @return array{content: string, rows: int}
     */
    public function write(iterable $batches): array
    {
        $content = $this->line([
            (string) __('Kind of page'),
            (string) __('ID'),
            (string) __('Name'),
            (string) __('SKU'),
            (string) __('Attribute set'),
            (string) __('Store view ID'),
        ]);
        $rows = 0;
        foreach ($batches as $items) {
            foreach ($items as $item) {
                $content .= $this->line([
                    $item->getKind(),
                    (string) ($item->getEntityId() ?? ''),
                    $item->getLabel(),
                    $item->getSku() ?? '',
                    $item->getGroup() ?? '',
                    (string) $item->getStoreId(),
                ]);
                $rows++;
            }
        }

        return ['content' => $content, 'rows' => $rows];
    }

    /**
     * A cell a spreadsheet reads as text
     *
     * @param string $value
     * @return string
     */
    public function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_STARTS, true) ? self::QUOTE . $value : $value;
    }

    /**
     * One line of safe cells, each in double quotes when it holds a comma, a quote, a space, a tab or a line break
     *
     * @param list<string> $cells
     * @return string
     */
    private function line(array $cells): string
    {
        return implode(',', array_map(function (string $value): string {
            $cell = $this->cell($value);
            if (strpbrk($cell, self::NEEDS_QUOTES) === false) {
                return $cell;
            }

            return self::ENCLOSURE
                . str_replace(self::ENCLOSURE, self::ENCLOSURE . self::ENCLOSURE, $cell)
                . self::ENCLOSURE;
        }, $cells)) . "\n";
    }
}
