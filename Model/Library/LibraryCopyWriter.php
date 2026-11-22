<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Api\JsonEncoderInterface;
use Hryvinskyi\RichSnippets\Api\CodeAllocatorInterface;
use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\OriginFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;

/**
 * Saves one copy of a library template: its answers put in, its includes pointed at the shop's building blocks
 *
 * The copy is switched off, named after the template with the first free number, coded by the code allocator,
 * placed as the template says in every store view, and remembers the template's code and version. Its template is
 * written as indented JSON, two spaces a level, as the Code tab shows it.
 */
class LibraryCopyWriter
{
    private const ENCODE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    private const INDENT_PATTERN = '/^(?: {4})+/m';

    /**
     * @param AnswerSubstitution $substitution
     * @param JsonEncoderInterface $json
     * @param CopyNames $names
     * @param CodeAllocatorInterface $codes
     * @param SnippetFactoryInterface $snippets
     * @param TemplateTypeInterface $types
     * @param PlacementVocabularyInterface $vocabulary
     * @param OriginFactoryInterface $origins
     * @param SnippetRootRepositoryInterface $repository
     */
    public function __construct(
        private readonly AnswerSubstitution $substitution,
        private readonly JsonEncoderInterface $json,
        private readonly CopyNames $names,
        private readonly CodeAllocatorInterface $codes,
        private readonly SnippetFactoryInterface $snippets,
        private readonly TemplateTypeInterface $types,
        private readonly PlacementVocabularyInterface $vocabulary,
        private readonly OriginFactoryInterface $origins,
        private readonly SnippetRootRepositoryInterface $repository
    ) {
    }

    /**
     * Save a copy of a template
     *
     * @param LibraryTemplateInterface $template
     * @param array<string,AnswerOptionInterface> $answers by question code
     * @param array<string,string> $includes the shop's snippet code by building block library code
     * @param array<string,true> $takenNames the names in use
     * @return SnippetInterface the copy as stored
     * @throws SnippetRuleException|CompileException when the copy breaks a snippet rule
     */
    public function write(
        LibraryTemplateInterface $template,
        array $answers,
        array $includes,
        array $takenNames
    ): SnippetInterface {
        $name = $this->names->next($template->getName(), $takenNames);
        $copy = $this->snippets->draft($this->codes->allocate($name), $name, $template->isBlock());
        $copy->changeTemplate($this->source($template, $answers, $includes), $this->types);
        if (!$template->isBlock()) {
            $copy->place(
                $template->getPageKinds(),
                $template->getConditions(),
                [SnippetInterface::ALL_STORES],
                $this->vocabulary
            );
        }
        $copy->recordOrigin($this->origins->create($template->getCode(), $template->getVersion()));

        return $this->repository->save($copy);
    }

    /**
     * The copy's template source
     *
     * @param LibraryTemplateInterface $template
     * @param array<string,AnswerOptionInterface> $answers
     * @param array<string,string> $includes
     * @return string
     */
    private function source(LibraryTemplateInterface $template, array $answers, array $includes): string
    {
        $json = $this->json->encode(
            $this->substitution->substitute($template->getTemplate(), $answers, $includes),
            self::ENCODE_FLAGS
        );

        return (string) preg_replace_callback(
            self::INDENT_PATTERN,
            static fn (array $indent): string => str_repeat(' ', intdiv(strlen($indent[0]), 2)),
            $json
        );
    }
}
