<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\AdminUi\Api\Page\ModuleTabCountInterface;
use Hryvinskyi\RichSnippets\Api\SnippetListInterface;

/**
 * The count beside the Snippets tab: how many snippets need attention, those with a bad or a warning flag
 *
 * The tab is the same on every page of the module, so the count is taken over every snippet of every store view,
 * whichever store view a page shows. It is the number the Snippets list's "Needs attention" chip shows for
 * "All store views".
 */
class AttentionTabCount implements ModuleTabCountInterface
{
    /**
     * @param SnippetListInterface $snippets
     */
    public function __construct(private readonly SnippetListInterface $snippets)
    {
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->snippets->list(null)->getAttentionCount();
    }
}
