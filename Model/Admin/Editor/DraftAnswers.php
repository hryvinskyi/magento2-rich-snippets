<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\PageSearchInterface;
use Hryvinskyi\RichSnippets\Api\SnippetDraftInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Checks and previews the draft a request carries
 *
 * The check answers `{"problems"}` (see EditorPresenter); the preview answers as PreviewPresenter writes it, each
 * check action that puts a property back carrying the property's value from the library (see CheckActionValues). A
 * draft of a snippet that is not stored answers 400 `{"error": "id"}`.
 *
 * @phpstan-import-type Problem from EditorPresenter
 * @phpstan-import-type Answer from PreviewPresenter
 */
class DraftAnswers
{
    /**
     * @param EditorRequest $request
     * @param SnippetDraftInterface $drafts
     * @param PageSearchInterface $pages
     * @param TemplateTypeInterface $types
     * @param EditorPresenter $presenter
     * @param PreviewPresenter $previews
     * @param CheckActionValues $actions
     */
    public function __construct(
        private readonly EditorRequest $request,
        private readonly SnippetDraftInterface $drafts,
        private readonly PageSearchInterface $pages,
        private readonly TemplateTypeInterface $types,
        private readonly EditorPresenter $presenter,
        private readonly PreviewPresenter $previews,
        private readonly CheckActionValues $actions
    ) {
    }

    /**
     * Everything wrong with the draft
     *
     * @param array<mixed> $body `{"snippet"}`
     * @return array{problems: list<Problem>}
     * @throws InvalidWizardRequestException
     */
    public function validate(array $body): array
    {
        $draft = $this->request->draft($body);
        try {
            return ['problems' => $this->presenter->problems($this->drafts->validate($draft))];
        } catch (NoSuchEntityException $e) {
            throw new InvalidWizardRequestException('id', 'The snippet is not stored.', $e);
        }
    }

    /**
     * The draft on a sample page
     *
     * @param array<mixed> $body `{"snippet", "sample", "store"}`
     * @return Answer
     * @throws InvalidWizardRequestException
     * @throws MalformedLibraryTemplateException|LibraryException|\JsonException
     */
    public function preview(array $body): array
    {
        $draft = $this->request->draft($body);
        $page = $this->request->sample($body);
        try {
            $preview = $this->drafts->preview($draft, $page);
        } catch (NoSuchEntityException $e) {
            throw new InvalidWizardRequestException('id', 'The snippet is not stored.', $e);
        }

        $type = $this->types->typesOf($draft->getTemplate())[0] ?? '';
        $answer = $this->previews->present($preview, $this->pages->find($page), $type);
        $answer['checks'] = $this->actions->fill(
            $answer['checks'],
            $draft->getTemplate(),
            $type,
            $draft->getId(),
            $page->getStoreId()
        );

        return $answer;
    }
}
