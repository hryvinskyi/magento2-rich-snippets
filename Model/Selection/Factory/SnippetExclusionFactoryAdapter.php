<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Selection\Factory;

use Hryvinskyi\RichSnippets\Api\Data\ExclusionReason;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Model\Selection\SnippetExclusionFactoryInterface;

/**
 * Delegates to the generated factory of the snippet exclusion contract
 */
class SnippetExclusionFactoryAdapter implements SnippetExclusionFactoryInterface
{
    /**
     * @param SnippetExclusionInterfaceFactory $generated
     */
    public function __construct(private readonly SnippetExclusionInterfaceFactory $generated)
    {
    }

    /**
     * @inheritDoc
     */
    public function create(
        SnippetInterface $snippet,
        ExclusionReason $reason,
        array $mismatches = []
    ): SnippetExclusionInterface {
        return $this->generated->create(['snippet' => $snippet, 'reason' => $reason, 'mismatches' => $mismatches]);
    }
}
