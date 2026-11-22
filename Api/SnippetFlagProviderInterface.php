<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * Puts flags on the rows of the snippet list: one source of remarks, such as a required property no page can have
 *
 * Providers are items of the flag provider pool (di.xml); another module adds one by adding an item there. Each is
 * asked once per listing, for every snippet listed, so it can read what it needs in one go.
 *
 * @api
 */
interface SnippetFlagProviderInterface
{
    /**
     * The flags of each snippet listed, in the order they are shown
     *
     * @param list<SnippetInterface> $snippets
     * @param int|null $storeId the store view the list is for; null for every store view
     * @return array<string,list<SnippetFlagInterface>> by snippet code; a snippet without flags may be left out
     */
    public function flags(array $snippets, ?int $storeId): array;
}
