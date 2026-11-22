<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Write;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;

/**
 * Overwrites the shop's snippet with the file's code: its name, template, kinds of page, conditions and origin
 *
 * It keeps its switch, its store views and its place in the list. Turned into a building block, it loses its switch
 * and placement as any building block does; turned into a page snippet, it arrives switched off.
 */
class ReplaceWriter implements ImportChoiceWriterInterface
{
    /**
     * @param SnippetRootRepositoryInterface $repository
     * @param TemplateTypeInterface $types
     * @param PlacementVocabularyInterface $vocabulary
     */
    public function __construct(
        private readonly SnippetRootRepositoryInterface $repository,
        private readonly TemplateTypeInterface $types,
        private readonly PlacementVocabularyInterface $vocabulary
    ) {
    }

    /**
     * @inheritDoc
     */
    public function write(array $analysed): ?SnippetInterface
    {
        $entry = $analysed['entry'];
        $snippet = $this->repository->getByCode($entry['code']);
        $snippet->rename($entry['name']);
        if ($entry['block'] && !$snippet->isBlock()) {
            $snippet->turnIntoBlock($analysed['checked']['conditions']);
        }
        if (!$entry['block'] && $snippet->isBlock()) {
            $snippet->turnIntoPageSnippet();
        }
        $snippet->changeTemplate($entry['template'], $this->types);
        if (!$entry['block']) {
            $snippet->place(
                $entry['pageKinds'],
                $analysed['checked']['conditions'],
                $snippet->getStoreIds(),
                $this->vocabulary
            );
        }
        $snippet->recordOrigin($analysed['checked']['origin']);

        return $this->repository->save($snippet);
    }
}
