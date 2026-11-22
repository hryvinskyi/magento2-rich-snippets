<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Reason;

use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;

/**
 * The snippet is set for store views other than the page's: "Set for other store views."
 */
class OtherStoresWording implements ExclusionWordingInterface
{
    /**
     * @inheritDoc
     */
    public function text(SnippetExclusionInterface $exclusion, int $storeId): string
    {
        return (string) __('Set for other store views.');
    }
}
