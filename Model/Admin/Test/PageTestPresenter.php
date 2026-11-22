<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test;

use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageTestResultInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorPresenter;

/**
 * Writes a page test as the Test page's "One page" mode reads it
 *
 * `{"page": {"ref", "kind", "label", "path", "readPath"}, "store", "testedAt", "durationMs", "failure",
 * "blocks", "themeMarkup", "notOutput", "features"}`: `ref` is how the page picker names the page (`product:12`),
 * null for a page given by its path; `readPath` the path the page was read at after redirects; `testedAt` an ISO
 * 8601 time in UTC; `failure` is null for a page that was read, else `{"reason", "httpStatus", "text"}`; `blocks`
 * see BlockRows; `themeMarkup` is `{"found", "items": [{"type", "properties"}], "text"}`; `notOutput` see
 * NotOutputReasons; `features` see FeatureRows. A failed test has no blocks, no theme markup, nothing listed as not
 * output and no features.
 *
 * @phpstan-import-type Block from BlockRows
 * @phpstan-import-type Row from NotOutputReasons
 * @phpstan-import-type Feature from FeatureRows
 * @phpstan-type Answer array{page: array{ref: string|null, kind: string, label: string, path: string,
 *     readPath: string|null}, store: int, testedAt: string, durationMs: int,
 *     failure: array{reason: string, httpStatus: int|null, text: string}|null, blocks: list<Block>,
 *     themeMarkup: array{found: bool, items: list<array{type: string, properties: list<string>}>, text: string},
 *     notOutput: list<Row>, features: list<Feature>}
 */
class PageTestPresenter
{
    /**
     * @param EditorPresenter $editor
     * @param BlockRows $blocks
     * @param FeatureRows $features
     * @param NotOutputReasons $reasons
     * @param ReadFailurePhrases $failures
     * @param WordList $words
     */
    public function __construct(
        private readonly EditorPresenter $editor,
        private readonly BlockRows $blocks,
        private readonly FeatureRows $features,
        private readonly NotOutputReasons $reasons,
        private readonly ReadFailurePhrases $failures,
        private readonly WordList $words
    ) {
    }

    /**
     * The test as the page reads it
     *
     * @param PageTestResultInterface $result
     * @return Answer
     */
    public function present(PageTestResultInterface $result): array
    {
        $page = $result->getPage();
        $failure = $result->getFailure();
        $blocks = $result->getBlocks();

        return [
            'page' => [
                'ref' => $page->getEntityId() === null ? null : $this->editor->reference($page),
                'kind' => $page->getKind(),
                'label' => $result->getLabel(),
                'path' => $result->getPath(),
                'readPath' => $result->getReadPath(),
            ],
            'store' => $page->getStoreId(),
            'testedAt' => gmdate(DATE_ATOM, $result->getTestedAt()),
            'durationMs' => $result->getDurationMs(),
            'failure' => $failure === null ? null : [
                'reason' => $failure->value,
                'httpStatus' => $result->getHttpStatus(),
                'text' => $this->failures->text($failure, $result->getHttpStatus()),
            ],
            'blocks' => $this->blocks->rows($blocks),
            'themeMarkup' => $this->themeMarkup($result->getThemeMarkup()),
            'notOutput' => $this->reasons->rows(
                $result->getExclusions(),
                $result->getUnwritten(),
                $page->getStoreId()
            ),
            'features' => $this->features->rows($blocks),
        ];
    }

    /**
     * The theme's own microdata, with the notice the page shows about it
     *
     * @param list<MicrodataItemInterface> $items
     * @return array{found: bool, items: list<array{type: string, properties: list<string>}>, text: string}
     */
    private function themeMarkup(array $items): array
    {
        $first = $items[0] ?? null;
        $text = '';
        if ($first !== null) {
            $properties = $this->words->join($first->getProperties());
            $text = (string) ($properties === ''
                ? __(
                    'The theme writes its own %1 microdata into the HTML of this page. Two descriptions of one'
                    . ' thing can disagree, and Search Console then reports both.',
                    $first->getType()
                )
                : __(
                    'The theme writes its own %1 microdata here: %2, in the HTML of this page. Two descriptions of'
                    . ' one thing can disagree, and Search Console then reports both.',
                    $first->getType(),
                    $properties
                ));
        }

        return [
            'found' => $items !== [],
            'items' => array_map(
                static fn (MicrodataItemInterface $item): array => [
                    'type' => $item->getType(),
                    'properties' => $item->getProperties(),
                ],
                $items
            ),
            'text' => $text,
        ];
    }
}
