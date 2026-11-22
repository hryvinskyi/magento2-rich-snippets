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
 * Words one reason why a page snippet is not output on a tested page
 */
interface ExclusionWordingInterface
{
    /**
     * The sentence that says why the snippet is not output on the page
     *
     * @param SnippetExclusionInterface $exclusion
     * @param int $storeId the tested page's store view, whose labels the sentence uses
     * @return string
     */
    public function text(SnippetExclusionInterface $exclusion, int $storeId): string;
}
