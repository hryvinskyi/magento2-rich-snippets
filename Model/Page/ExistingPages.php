<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page;

use Hryvinskyi\RichSnippets\Api\ExistingPagesInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Magento\Framework\DB\Select;

/**
 * The pages a store view has, from one source per kind listed in the container configuration
 *
 * A kind with no source is one whose pages cannot be listed; an item that is not a source is a configuration error,
 * refused when the pool is built.
 */
class ExistingPages implements ExistingPagesInterface
{
    /**
     * @var array<string, ExistingPageSourceInterface>
     */
    private array $sources = [];

    /**
     * @param PageKindPoolInterface $kinds
     * @param array<array-key,mixed> $sources the sources by kind code
     * @throws \InvalidArgumentException
     */
    public function __construct(private readonly PageKindPoolInterface $kinds, array $sources = [])
    {
        foreach ($sources as $kind => $source) {
            if (!$source instanceof ExistingPageSourceInterface) {
                throw new \InvalidArgumentException(sprintf('The pages of the kind "%s" have no source.', $kind));
            }
            $this->sources[(string) $kind] = $source;
        }
    }

    /**
     * @inheritDoc
     */
    public function select(string $kind, int $storeId): ?Select
    {
        if (!$this->kinds->has($kind)) {
            throw new \InvalidArgumentException(sprintf('No kind of page has the code "%s".', $kind));
        }
        $source = $this->sources[$kind] ?? null;

        return $source?->select($storeId);
    }
}
