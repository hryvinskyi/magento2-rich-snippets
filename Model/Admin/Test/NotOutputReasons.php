<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test;

use Hryvinskyi\RichSnippets\Api\Data\SnippetExclusionInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenReason;
use Hryvinskyi\RichSnippets\Api\Data\UnwrittenSnippetInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Test\Reason\ExclusionWordings;

/**
 * Words why a snippet is not output on a tested page, as the page test's list of snippets not output says it
 *
 * A snippet not set for the page is worded by the wording its reason has in the pool ("Set for product pages and CMS
 * pages.", "Set for product type Configurable; this one is Simple."); a snippet set for the page that it does not
 * carry says why it is missing.
 *
 * @phpstan-type Row array{code: string, snippetId: int|null, name: string, reason: string, text: string}
 */
class NotOutputReasons
{
    /**
     * @param ExclusionWordings $wordings
     */
    public function __construct(private readonly ExclusionWordings $wordings)
    {
    }

    /**
     * The rows of the snippets not set for the page, then of those set for it that it does not carry
     *
     * @param list<SnippetExclusionInterface> $exclusions
     * @param list<UnwrittenSnippetInterface> $unwritten
     * @param int $storeId the tested page's store view, whose labels the reasons use
     * @return list<Row>
     */
    public function rows(array $exclusions, array $unwritten, int $storeId): array
    {
        $rows = [];
        foreach ($exclusions as $exclusion) {
            $rows[] = $this->row(
                $exclusion->getSnippet(),
                $exclusion->getReason()->value,
                $this->wordings->text($exclusion, $storeId)
            );
        }
        foreach ($unwritten as $missing) {
            $rows[] = $this->row(
                $missing->getSnippet(),
                $missing->getReason()->value,
                $this->unwritten($missing->getReason())
            );
        }

        return $rows;
    }

    /**
     * Why a snippet set for the page is not on it
     *
     * @param UnwrittenReason $reason
     * @return string
     */
    private function unwritten(UnwrittenReason $reason): string
    {
        return (string) match ($reason) {
            UnwrittenReason::SnippetsOff => __('Snippets are switched off for this store view in Settings.'),
            UnwrittenReason::Empty => __('Every property is empty on this page, so nothing is written.'),
            UnwrittenReason::NotInPage => __(
                'The storefront writes it now, but the page as read does not carry it. The page cache may still'
                . ' hold an older copy.'
            ),
        };
    }

    /**
     * One row
     *
     * @param SnippetInterface $snippet
     * @param string $reason
     * @param string $text
     * @return Row
     */
    private function row(SnippetInterface $snippet, string $reason, string $text): array
    {
        return [
            'code' => $snippet->getCode(),
            'snippetId' => $snippet->getId(),
            'name' => $snippet->getName(),
            'reason' => $reason,
            'text' => $text,
        ];
    }
}
