<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Reason;

use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Model\Placement\KindPhrases;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;

/**
 * The snippet is set for other kinds of page, named by their phrases: "Set for product pages and CMS pages."
 */
class OtherKindsWording implements ExclusionWordingInterface
{
    /**
     * @param WordList $words
     * @param KindPhrases $kindPhrases
     */
    public function __construct(
        private readonly WordList $words,
        private readonly KindPhrases $kindPhrases
    ) {
    }

    /**
     * @inheritDoc
     */
    public function text(SnippetExclusionInterface $exclusion, int $storeId): string
    {
        return (string) __(
            'Set for %1.',
            $this->words->join(array_map(
                fn (string $kind): string => $this->kindPhrases->phrase($kind),
                $exclusion->getSnippet()->getPageKinds()
            ))
        );
    }
}
