<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\AdminUi\Api\Page\KeyedAdminUrlInterface;
use Hryvinskyi\AdminUi\Api\Page\PageContentInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\BuilderConfig;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Fields;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\IncludeCandidates;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Load;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\PlacementCount;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Preview;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Samples;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Save;
use Hryvinskyi\RichSnippets\Controller\Adminhtml\Snippets\Validate;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsRequest;
use Hryvinskyi\RichSnippets\Model\Placement\KindLabels;
use Hryvinskyi\RichSnippets\Model\Placement\KindPhrases;
use Magento\Framework\App\RequestInterface;

/**
 * What the snippet editor hands its island: the snippet the page opens — a stored one, by the request's `id`, or a
 * blank one for a new snippet — with the snippets that include it and the condition fields of each kind of page, so
 * the editor draws without a round trip; the kinds of page; the types a blank snippet suggests; and the values a
 * property starts with when its kind of value is changed
 *
 * The data is `{"snippet", "isNew", "section", "usedIn", "vocabulary", "kinds", "typeSuggestions", "defaults"}`:
 * `snippet`, `usedIn` and `vocabulary` as the load endpoint answers them (see EditorDocument), a blank snippet in the
 * same shape; `section` the part the editor opens on — `pages` when the address asks for it (`section/pages`), else
 * `properties`; `kinds` as the Snippets page lists them, with the label of one of their pages
 * (`{"code", "label", "phrase", "page", "icon"}`); `defaults` is `{"text": a sentence
 * that mixes text and fields, "code": a template part, "when": a field path, "list": {"each": a collection path,
 * "item": a field path}}`. The endpoints are the editor's: load, save, the draft's check and preview, the field
 * catalog, the sample pages, the snippets it can include, the builder's configuration and the placement count. The
 * page links to the snippet list, the editor of another snippet, the library and the page test.
 *
 * @phpstan-import-type Document from EditorPresenter
 * @phpstan-type Kind array{code: string, label: string, phrase: string, page: string, icon: string}
 * @phpstan-type Defaults array{text: string, code: string, when: string, list: array{each: string, item: string}}
 */
class EditorPage implements PageContentInterface
{
    /** The screens the editor links to, by route path */
    public const ROUTES = [
        'hryvinskyi_richsnippets/snippets/index',
        'hryvinskyi_richsnippets/snippets/edit',
        'hryvinskyi_richsnippets/library/index',
        'hryvinskyi_richsnippets/test/index',
    ];

    private const BLANK_TEMPLATE = "{\n  \"@context\": \"https://schema.org\"\n}";

    /** The parameter naming the part the editor opens on, and the parts it can name */
    private const SECTION = 'section';
    private const SECTIONS = ['properties', 'pages'];

    /**
     * @param EditorDocument $documents
     * @param RequestInterface $request
     * @param PageKindPoolInterface $kinds
     * @param KindLabels $labels
     * @param KindPhrases $phrases
     * @param KeyedAdminUrlInterface $urls
     * @param bool $newSnippet whether the page opens a blank snippet, whatever the request names
     * @param string[] $typeSuggestions schema.org types a blank snippet offers to start from
     * @param string[] $blankKinds the kinds of page a blank snippet starts set for
     * @param string $textDefault a sentence that mixes text and fields, source English, translated when read
     * @param string $codeDefault a template part a property set to code starts with
     * @param string $whenDefault the field a property's condition starts with
     * @param string $listCollection the collection a property set to a list starts with
     * @param string $listItem the field each item of that list starts with
     */
    public function __construct(
        private readonly EditorDocument $documents,
        private readonly RequestInterface $request,
        private readonly PageKindPoolInterface $kinds,
        private readonly KindLabels $labels,
        private readonly KindPhrases $phrases,
        private readonly KeyedAdminUrlInterface $urls,
        private readonly bool $newSnippet = false,
        private readonly array $typeSuggestions = [],
        private readonly array $blankKinds = [],
        private readonly string $textDefault = '',
        private readonly string $codeDefault = '',
        private readonly string $whenDefault = '',
        private readonly string $listCollection = '',
        private readonly string $listItem = ''
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getUrls(): array
    {
        return [
            'load' => $this->urls->getUrl(Load::ROUTE),
            'save' => $this->urls->getUrl(Save::ROUTE),
            'validate' => $this->urls->getUrl(Validate::ROUTE),
            'preview' => $this->urls->getUrl(Preview::ROUTE),
            'fields' => $this->urls->getUrl(Fields::ROUTE),
            'samples' => $this->urls->getUrl(Samples::ROUTE),
            'includeCandidates' => $this->urls->getUrl(IncludeCandidates::ROUTE),
            'builderConfig' => $this->urls->getUrl(BuilderConfig::ROUTE),
            'placementCount' => $this->urls->getUrl(PlacementCount::ROUTE),
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
     * @return array{snippet: Document, isNew: bool, section: string,
     *     usedIn: list<array{id: int|null, code: string, name: string, paths: list<string>}>,
     *     vocabulary: array{conditions: array<string, list<array{code: string, label: string}>>},
     *     kinds: list<Kind>, typeSuggestions: list<string>, defaults: Defaults}
     * @throws \Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException When the request names a
     *     snippet that is not stored
     */
    public function getData(): array
    {
        $id = $this->newSnippet ? '' : $this->request->getParam(SnippetsRequest::ID, '');
        $loaded = $this->documents->load([SnippetsRequest::ID => is_scalar($id) ? (string) $id : '']);
        $snippet = $loaded['snippet'];

        return [
            'snippet' => $snippet ?? $this->blank(),
            'isNew' => $snippet === null,
            'section' => $this->section(),
            'usedIn' => $loaded['usedIn'],
            'vocabulary' => $loaded['vocabulary'],
            'kinds' => array_map(
                fn (string $kind): array => [
                    'code' => $kind,
                    'label' => $this->labels->label($kind),
                    'phrase' => $this->phrases->phrase($kind),
                    'page' => $this->labels->pageLabel($kind),
                    'icon' => $this->labels->icon($kind),
                ],
                $this->kinds->getCodes()
            ),
            'typeSuggestions' => array_values($this->typeSuggestions),
            'defaults' => [
                'text' => (string) __($this->textDefault),
                'code' => $this->codeDefault,
                'when' => $this->whenDefault,
                'list' => ['each' => $this->listCollection, 'item' => $this->listItem],
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getLoadingText(): string
    {
        return (string) __('Loading the snippet…');
    }

    /**
     * @inheritDoc
     */
    public function getFailedText(): string
    {
        return (string) __('The snippet could not be loaded. Reload the page to try again.');
    }

    /**
     * The part the editor opens on: the one the address names, else the properties
     *
     * @return string
     */
    private function section(): string
    {
        $section = $this->request->getParam(self::SECTION, '');

        return in_array($section, self::SECTIONS, true) ? $section : self::SECTIONS[0];
    }

    /**
     * A snippet not saved yet: no name, no type, switched off, every store view
     *
     * @return Document
     */
    private function blank(): array
    {
        return [
            'id' => null,
            'code' => '',
            'name' => '',
            'type' => '',
            'template' => self::BLANK_TEMPLATE,
            'isBlock' => false,
            'active' => false,
            'pageKinds' => array_values(array_filter(
                $this->blankKinds,
                fn (string $kind): bool => $this->kinds->has($kind)
            )),
            'conditions' => '',
            'storeIds' => [0],
            'origin' => null,
            'updatedAt' => null,
            'updatedBy' => '',
        ];
    }
}
