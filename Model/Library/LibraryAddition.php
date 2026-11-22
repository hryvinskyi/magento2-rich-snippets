<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\AnswerOptionInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\LibraryTemplatePoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;

/**
 * Adds copies of library templates together with the building blocks they use
 *
 * A building block a template uses is the shop's snippet added from that block's template: reused when the shop has
 * one (a building block before a page snippet, else the first the repository lists), otherwise added once, with its
 * own suggested answers, before the copy that uses it. Each snippet the add ends with is listed once, as a record
 * that survives a JSON round trip, so an idempotent replay answers the same list.
 */
class LibraryAddition
{
    public const ID = 'id';
    public const CODE = 'code';
    public const NAME = 'name';
    public const BLOCK = 'block';
    public const TEMPLATE = 'template';
    public const REUSED = 'reused';

    /**
     * @param LibraryTemplatePoolInterface $templates
     * @param AnswerResolver $answers
     * @param LibraryCopyWriter $writer
     * @param SnippetRepositoryInterface $snippets
     * @param AdditionRunFactory $runs
     */
    public function __construct(
        private readonly LibraryTemplatePoolInterface $templates,
        private readonly AnswerResolver $answers,
        private readonly LibraryCopyWriter $writer,
        private readonly SnippetRepositoryInterface $snippets,
        private readonly AdditionRunFactory $runs
    ) {
    }

    /**
     * Add a copy of each template, in order
     *
     * @param list<array{LibraryTemplateInterface,array<string,AnswerOptionInterface>}> $requests each template with
     *     its chosen answers by question code
     * @param int $storeId the store view the answers of a building block added on the way are suggested for
     * @return list<array{id: int, code: string, name: string, block: bool, template: string, reused: bool}>
     * @throws LibraryException|SnippetRuleException|CompileException|MalformedLibraryTemplateException
     */
    public function add(array $requests, int $storeId): array
    {
        $run = $this->runs->create();
        foreach ($this->snippets->list() as $snippet) {
            $run->know($snippet);
        }
        foreach ($requests as [$template, $chosen]) {
            $this->addTemplate($run, $template, $chosen, $storeId, []);
        }

        return $run->records();
    }

    /**
     * Add a copy of a template after the building blocks it needs
     *
     * @param AdditionRun $run
     * @param LibraryTemplateInterface $template
     * @param array<string,AnswerOptionInterface> $chosen
     * @param int $storeId
     * @param array<string,true> $adding the codes of the templates being added further up, to stop a cycle
     * @return SnippetInterface
     * @throws LibraryException|SnippetRuleException|CompileException|MalformedLibraryTemplateException
     */
    private function addTemplate(
        AdditionRun $run,
        LibraryTemplateInterface $template,
        array $chosen,
        int $storeId,
        array $adding
    ): SnippetInterface {
        $adding[$template->getCode()] = true;
        $includes = [];
        foreach ($template->getUses() as $use) {
            $includes[$use] = $this->block($run, $use, $storeId, $adding)->getCode();
        }
        $copy = $this->writer->write($template, $chosen, $includes, $run->takenNames());
        $run->created($copy, $template->getCode());

        return $copy;
    }

    /**
     * The shop's building block of a library code: the one it has, or one added now
     *
     * @param AdditionRun $run
     * @param string $code
     * @param int $storeId
     * @param array<string,true> $adding
     * @return SnippetInterface
     * @throws LibraryException|SnippetRuleException|CompileException|MalformedLibraryTemplateException
     */
    private function block(AdditionRun $run, string $code, int $storeId, array $adding): SnippetInterface
    {
        $existing = $run->blockOf($code);
        if ($existing !== null) {
            $run->reused($existing, $code);

            return $existing;
        }
        $template = $this->templates->get($code);
        if ($template === null || isset($adding[$code])) {
            throw new MalformedLibraryTemplateException(
                sprintf('The building block "%s" is not in the library, or uses itself.', $code)
            );
        }

        return $this->addTemplate($run, $template, $this->answers->chosen($template, [], $storeId), $storeId, $adding);
    }
}
