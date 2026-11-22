<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\RichSnippets\Api\ConditionTreePrinterInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageMatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * Writes the values the editor's answers share: a snippet as the editor holds it (its conditions the expression
 * builder's text), problems, a sample page
 *
 * @phpstan-type Document array{id: int|null, code: string, name: string, type: string, template: string,
 *     isBlock: bool, active: bool, pageKinds: list<string>, conditions: string, storeIds: list<int>,
 *     origin: array{code: string, version: int}|null, updatedAt: string|null, updatedBy: string}
 * @phpstan-type Problem array{part: string, path: string, code: string,
 *     params: array<string, scalar|null>|\stdClass, offset: int|null, blocking: bool, message: string}
 * @phpstan-type Sample array{ref: string, kind: string, id: int|null, label: string, url: string, path: string}
 */
class EditorPresenter
{
    private const PATH_OF_URL = '#\A[a-z][a-z0-9+.-]*://[^/?\#]*([^?\#]*)#i';

    /**
     * @param ProblemPhrases $phrases
     * @param ConditionTreePrinterInterface $printer
     */
    public function __construct(
        private readonly ProblemPhrases $phrases,
        private readonly ConditionTreePrinterInterface $printer
    ) {
    }

    /**
     * A stored snippet as the editor loads it
     *
     * @param SnippetInterface $snippet
     * @return Document
     */
    public function snippet(SnippetInterface $snippet): array
    {
        $origin = $snippet->getOrigin();

        return [
            'id' => $snippet->getId(),
            'code' => $snippet->getCode(),
            'name' => $snippet->getName(),
            'type' => $snippet->getType(),
            'template' => $snippet->getTemplate(),
            'isBlock' => $snippet->isBlock(),
            'active' => $snippet->isActive(),
            'pageKinds' => $snippet->getPageKinds(),
            'conditions' => $this->printer->print($snippet->getConditions()),
            'storeIds' => $snippet->getStoreIds(),
            'origin' => $origin === null ? null : ['code' => $origin->getCode(), 'version' => $origin->getVersion()],
            'updatedAt' => $snippet->getUpdatedAt(),
            'updatedBy' => $snippet->getUpdatedBy(),
        ];
    }

    /**
     * Problems, each with its sentence; parameters are written as a JSON object even when there are none
     *
     * @param list<DraftProblemInterface> $problems
     * @return list<Problem>
     */
    public function problems(array $problems): array
    {
        return array_map(
            fn (DraftProblemInterface $problem): array => [
                'part' => $problem->getPart(),
                'path' => $problem->getPath(),
                'code' => $problem->getCode(),
                'params' => $problem->getParams() === [] ? (object) [] : $problem->getParams(),
                'offset' => $problem->getOffset(),
                'blocking' => $problem->isBlocking(),
                'message' => $this->phrases->sentence($problem),
            ],
            $problems
        );
    }

    /**
     * A page as the sample picker lists it
     *
     * @param PageMatchInterface $match
     * @return Sample
     */
    public function sample(PageMatchInterface $match): array
    {
        $page = $match->getPage();
        $url = $match->getUrl();

        return [
            'ref' => $this->reference($page),
            'kind' => $page->getKind(),
            'id' => $page->getEntityId(),
            'label' => $match->getLabel(),
            'url' => $url,
            'path' => preg_match(self::PATH_OF_URL, $url, $parts) === 1 ? $parts[1] : '',
        ];
    }

    /**
     * How a request names a page: `kind:id`
     *
     * @param PageEntityInterface $page
     * @return string
     */
    public function reference(PageEntityInterface $page): string
    {
        return $page->getKind() . ':' . $page->getEntityId();
    }
}
