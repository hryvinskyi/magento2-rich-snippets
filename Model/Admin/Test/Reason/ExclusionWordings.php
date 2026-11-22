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
 * The wording of each reason a page snippet is not output on a page, by the reason's value
 *
 * Each reason has a wording of its own, configured in the pool; a reason with none is an error in the configuration
 * and fails loudly rather than leaving the row without a sentence.
 */
class ExclusionWordings
{
    /**
     * @param array<string,ExclusionWordingInterface> $wordings by the reason's value
     */
    public function __construct(private readonly array $wordings = [])
    {
    }

    /**
     * The sentence that says why the snippet is not output on the page
     *
     * @param SnippetExclusionInterface $exclusion
     * @param int $storeId the tested page's store view
     * @return string
     * @throws \LogicException when no wording is configured for the reason
     */
    public function text(SnippetExclusionInterface $exclusion, int $storeId): string
    {
        $reason = $exclusion->getReason()->value;
        $wording = $this->wordings[$reason] ?? null;
        if (!$wording instanceof ExclusionWordingInterface) {
            throw new \LogicException(sprintf('No wording is configured for the reason "%s".', $reason));
        }

        return $wording->text($exclusion, $storeId);
    }
}
