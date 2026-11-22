<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\AdminUi\Api\Page\KeyedAdminUrlInterface;
use Hryvinskyi\AdminUi\Api\Page\PageContentInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Delete;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Duplicate;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Export;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\ImportCommit;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\ImportPreview;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\ListAction;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\SetActive;
use Hryvinskyi\RichSnippets\Model\Placement\KindLabels;
use Hryvinskyi\RichSnippets\Model\Placement\KindPhrases;

/**
 * What the Snippets page hands its island: the list of every store view as its endpoint answers it, so the page
 * draws without a round trip, with the kinds of page the list is filtered by and the line saying where a snippet is
 * written
 *
 * The data is `{"kinds": [{"code", "label", "phrase", "icon"}], "outputLine", "list"}`: each kind of page in its
 * configured order with its label for a chip, its name inside a sentence and its glyph; `list` is the snippet list
 * (see SnippetRowsPresenter). The endpoints are the list (`?store=<id>`), the switch, the copy, the delete, the export
 * (a file to save), and the import's preview and commit. The page links to the editor of a snippet and of a new one,
 * and to the library, each drawn only for an admin who may open it.
 *
 * @phpstan-import-type Listing from SnippetRowsPresenter
 * @phpstan-type Kind array{code: string, label: string, phrase: string, icon: string}
 */
class SnippetsPage implements PageContentInterface
{
    /** The screens the snippet list links to, by route path */
    public const ROUTES = [
        'hryvinskyi_richsnippets/snippets/new',
        'hryvinskyi_richsnippets/snippets/edit',
        'hryvinskyi_richsnippets/library/index',
    ];

    /**
     * @param SnippetsListing $listing
     * @param PageKindPoolInterface $kinds
     * @param KindLabels $labels
     * @param KindPhrases $phrases
     * @param OutputLine $outputLine
     * @param KeyedAdminUrlInterface $urls
     */
    public function __construct(
        private readonly SnippetsListing $listing,
        private readonly PageKindPoolInterface $kinds,
        private readonly KindLabels $labels,
        private readonly KindPhrases $phrases,
        private readonly OutputLine $outputLine,
        private readonly KeyedAdminUrlInterface $urls
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getUrls(): array
    {
        return [
            'list' => $this->urls->getUrl(ListAction::ROUTE),
            'setActive' => $this->urls->getUrl(SetActive::ROUTE),
            'duplicate' => $this->urls->getUrl(Duplicate::ROUTE),
            'delete' => $this->urls->getUrl(Delete::ROUTE),
            'export' => $this->urls->getUrl(Export::ROUTE),
            'importPreview' => $this->urls->getUrl(ImportPreview::ROUTE),
            'importCommit' => $this->urls->getUrl(ImportCommit::ROUTE),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getRoutes(): array
    {
        return self::ROUTES;
    }

    /**
     * @inheritDoc
     *
     * @return array{kinds: list<Kind>, outputLine: string, list: Listing}
     * @throws \Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException Never for every store view
     */
    public function getData(): array
    {
        return [
            'kinds' => array_map(
                fn (string $kind): array => [
                    'code' => $kind,
                    'label' => $this->labels->label($kind),
                    'phrase' => $this->phrases->phrase($kind),
                    'icon' => $this->labels->icon($kind),
                ],
                $this->kinds->getCodes()
            ),
            'outputLine' => $this->outputLine->line(),
            'list' => $this->listing->of([]),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getLoadingText(): string
    {
        return (string) __('Loading the snippets…');
    }

    /**
     * @inheritDoc
     */
    public function getFailedText(): string
    {
        return (string) __('The snippets could not be loaded. Reload the page to try again.');
    }
}
