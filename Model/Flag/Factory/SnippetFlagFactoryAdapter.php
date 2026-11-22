<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Flag\Factory;

use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\SnippetFlagFactoryInterface;

/**
 * Creates flags through the factory Magento generates for their interface
 */
class SnippetFlagFactoryAdapter implements SnippetFlagFactoryInterface
{
    /**
     * @param SnippetFlagInterfaceFactory $flags
     */
    public function __construct(private readonly SnippetFlagInterfaceFactory $flags)
    {
    }

    /**
     * @inheritDoc
     */
    public function create(FlagTone $tone, string $text): SnippetFlagInterface
    {
        return $this->flags->create(['tone' => $tone, 'text' => $text]);
    }
}
