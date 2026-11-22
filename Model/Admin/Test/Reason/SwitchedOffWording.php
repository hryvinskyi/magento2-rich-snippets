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
 * The snippet is switched off: "Switched off."
 */
class SwitchedOffWording implements ExclusionWordingInterface
{
    /**
     * @inheritDoc
     */
    public function text(SnippetExclusionInterface $exclusion, int $storeId): string
    {
        return (string) __('Switched off.');
    }
}
