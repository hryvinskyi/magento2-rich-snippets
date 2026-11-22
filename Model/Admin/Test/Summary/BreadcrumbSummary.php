<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Summary;

/**
 * A breadcrumb trail as its names, joined the way the trail reads (`Home › Bags › Field satchel`)
 *
 * The crumbs are taken in the order of their positions, list order breaking ties; a crumb's name is its own or its
 * item's. A crumb without a name is skipped.
 */
class BreadcrumbSummary implements BlockSummaryInterface
{
    private const SEPARATOR = ' › ';

    /**
     * @inheritDoc
     */
    public function summarize(array $object): string
    {
        $crumbs = $object['itemListElement'] ?? [];
        if (!is_array($crumbs) || !array_is_list($crumbs)) {
            return '';
        }
        $named = [];
        foreach ($crumbs as $index => $crumb) {
            if (!is_array($crumb) || $this->nameOf($crumb) === '') {
                continue;
            }
            $position = is_numeric($crumb['position'] ?? null) ? (float) $crumb['position'] : 0.0;
            $named[] = ['position' => $position, 'index' => $index, 'name' => $this->nameOf($crumb)];
        }
        usort(
            $named,
            static fn (array $a, array $b): int => [$a['position'], $a['index']] <=> [$b['position'], $b['index']]
        );

        return implode(self::SEPARATOR, array_column($named, 'name'));
    }

    /**
     * A crumb's name, its own or its item's
     *
     * @param array<mixed> $crumb
     * @return string
     */
    private function nameOf(array $crumb): string
    {
        $item = $crumb['item'] ?? null;
        $name = $crumb['name'] ?? (is_array($item) ? ($item['name'] ?? null) : null);

        return is_scalar($name) ? trim((string) $name) : '';
    }
}
