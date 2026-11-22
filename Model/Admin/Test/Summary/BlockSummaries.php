<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Summary;

/**
 * The summaries of the types that have one, listed by schema.org type in the container configuration
 *
 * A type without a summary has none to show. An item that is not a summary is a configuration error, refused when
 * the list is built.
 */
class BlockSummaries
{
    /**
     * @var array<string, BlockSummaryInterface>
     */
    private array $summaries = [];

    /**
     * @param array<array-key, mixed> $summaries by schema.org type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $summaries = [])
    {
        foreach ($summaries as $type => $summary) {
            if (!$summary instanceof BlockSummaryInterface) {
                throw new \InvalidArgumentException(sprintf('The summary of "%s" is not a block summary.', $type));
            }
            $this->summaries[(string) $type] = $summary;
        }
    }

    /**
     * The summary of an object of a type; empty when the type has none
     *
     * @param string $type
     * @param array<mixed> $object
     * @return string
     */
    public function of(string $type, array $object): string
    {
        return isset($this->summaries[$type]) ? $this->summaries[$type]->summarize($object) : '';
    }
}
