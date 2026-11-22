<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;

/**
 * Creates the flags a flag provider puts on snippets' rows
 *
 * @api
 */
interface SnippetFlagFactoryInterface
{
    /**
     * A flag
     *
     * @param FlagTone $tone
     * @param string $text translated
     * @return SnippetFlagInterface
     */
    public function create(FlagTone $tone, string $text): SnippetFlagInterface;
}
