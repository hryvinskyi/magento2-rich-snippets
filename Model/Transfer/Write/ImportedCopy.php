<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Write;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Model\Library\CopyNames;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;
use Hryvinskyi\RichSnippets\Model\Transfer\ImportAnalysis;

/**
 * Adds a snippet of an import file as a new snippet of the shop, switched off
 *
 * It takes the file's template, placement with this shop's store views and condition values, place in the order and
 * origin. Its name is the one asked for, with the first free number when a snippet has that name already.
 *
 * @phpstan-import-type AnalysedSnippet from ImportAnalysis
 */
class ImportedCopy
{
    /**
     * @param SnippetRootRepositoryInterface $repository
     * @param SnippetFactoryInterface $snippets
     * @param CopyNames $names
     * @param TemplateTypeInterface $types
     * @param PlacementVocabularyInterface $vocabulary
     */
    public function __construct(
        private readonly SnippetRootRepositoryInterface $repository,
        private readonly SnippetFactoryInterface $snippets,
        private readonly CopyNames $names,
        private readonly TemplateTypeInterface $types,
        private readonly PlacementVocabularyInterface $vocabulary
    ) {
    }

    /**
     * The first free name of a name's sequence
     *
     * @param string $name
     * @return string
     */
    public function freeName(string $name): string
    {
        $taken = [];
        foreach ($this->repository->list() as $snippet) {
            $taken[$snippet->getName()] = true;
        }

        return $this->names->next(trim($name), $taken);
    }

    /**
     * Save the snippet as a new one
     *
     * @param AnalysedSnippet $analysed
     * @param string $code
     * @param string $name a free name
     * @return SnippetInterface the snippet as stored
     * @throws SnippetRuleException|CompileException
     */
    public function save(array $analysed, string $code, string $name): SnippetInterface
    {
        $entry = $analysed['entry'];
        $copy = $this->snippets->draft($code, $name, $entry['block']);
        $copy->changeTemplate($entry['template'], $this->types);
        if (!$entry['block']) {
            $copy->place(
                $entry['pageKinds'],
                $analysed['checked']['conditions'],
                $analysed['checked']['storeIds'],
                $this->vocabulary
            );
        }
        $copy->moveTo(max(0, $entry['sortOrder']));
        $copy->recordOrigin($analysed['checked']['origin']);

        return $this->repository->save($copy);
    }
}
