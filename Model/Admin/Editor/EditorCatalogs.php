<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\CollectionPoolInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageMatchInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;
use Hryvinskyi\RichSnippets\Api\FieldCatalogInterface;
use Hryvinskyi\RichSnippets\Api\PageSearchInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Editor\FieldExamples;
use Hryvinskyi\RichSnippets\Model\Editor\IncludeCandidates;
use Hryvinskyi\RichSnippets\Model\Editor\PropertyEnums;
use Hryvinskyi\RichSnippets\Model\Editor\PropertySuggestions;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Answers what the editor's pickers read: the field catalog, the sample pages and the snippets a snippet can include
 *
 * Each read is for one store view: the one the request names, else the default store view. The labels of the fields,
 * their groups and the lists are translated into the admin's language as they are read.
 *
 * @phpstan-import-type Sample from EditorPresenter
 */
class EditorCatalogs
{
    private const SAMPLE_LIMIT = 10;

    /**
     * @param EditorRequest $request
     * @param EditorPresenter $presenter
     * @param FieldCatalogInterface $fields
     * @param CollectionPoolInterface $collections
     * @param FieldExamples $examples
     * @param PropertyEnums $enums
     * @param PropertySuggestions $suggestions
     * @param PageSearchInterface $pages
     * @param SnippetRepositoryInterface $snippets
     * @param IncludeCandidates $candidates
     */
    public function __construct(
        private readonly EditorRequest $request,
        private readonly EditorPresenter $presenter,
        private readonly FieldCatalogInterface $fields,
        private readonly CollectionPoolInterface $collections,
        private readonly FieldExamples $examples,
        private readonly PropertyEnums $enums,
        private readonly PropertySuggestions $suggestions,
        private readonly PageSearchInterface $pages,
        private readonly SnippetRepositoryInterface $snippets,
        private readonly IncludeCandidates $candidates
    ) {
    }

    /**
     * The field catalog, with each field's value on the sample page when the query names one
     *
     * `{"groups": [{"code", "label", "fields": [{"path", "label", "attributeCode", "pageKinds", "example"}]}],
     * "lists": [{"path", "label", "itemName"}], "enums": {property: [member]}, "suggestions": {type: {object path:
     * [{"name", "required", "field"}]}}, "namedFields": {type: {name: field}}, "sample"}`; a suggestion's `field` is
     * the field its value is taken from, or null, and `namedFields` the field a property of a name is taken from when
     * the name is typed in. A field without a value on the sample has a null example; without a sample every example
     * is null and `sample` is null.
     *
     * @param array<mixed> $query `{"sample", "store"}`
     * @return array{groups: list<array{code: string, label: string, fields: list<array{path: string,
     *     label: string, attributeCode: string|null, pageKinds: list<string>, example: mixed}>}>,
     *     lists: list<array{path: string, label: string, itemName: string}>,
     *     enums: array<string, list<string>>,
     *     suggestions: array<string, array<string, list<array{name: string, required: bool, field: string|null}>>>,
     *     namedFields: array<string, array<string, string>>|\stdClass,
     *     sample: Sample|null}
     * @throws InvalidWizardRequestException
     * @throws MalformedRuleException
     */
    public function fields(array $query): array
    {
        $page = $this->request->optionalSample($query);
        $match = $page === null ? null : $this->pages->find($page);
        $fields = $this->fields->getFields();
        $examples = $page === null || $match === null ? [] : $this->examples->onPage($page, $fields);
        $groups = [];
        foreach ($fields as $field) {
            $code = $field->getGroupCode();
            $groups[$code] ??= ['code' => $code, 'label' => (string) __($field->getGroupLabel()), 'fields' => []];
            $groups[$code]['fields'][] = [
                'path' => $field->getPath(),
                'label' => (string) __($field->getLabel()),
                'attributeCode' => $field->getAttributeCode(),
                'pageKinds' => $field->getPageKinds(),
                'example' => $examples[$field->getPath()] ?? null,
            ];
        }
        $lists = [];
        foreach ($this->collections->getCollections() as $collection) {
            $lists[] = [
                'path' => $collection->getPath(),
                'label' => (string) __($collection->getLabel()),
                'itemName' => $collection->getItemName(),
            ];
        }

        $namedFields = $this->suggestions->fieldsByName();

        return [
            'groups' => array_values($groups),
            'lists' => $lists,
            'enums' => $this->enums->all(),
            'suggestions' => $this->suggestions->byType(),
            'namedFields' => $namedFields === [] ? (object) [] : $namedFields,
            'sample' => $match === null ? null : $this->presenter->sample($match),
        ];
    }

    /**
     * The sample pages of some kinds whose name holds a text, those the snippet's conditions reach first
     *
     * The pages the conditions reach come first, so the first sample of a snippet limited to some pages is one of them;
     * the other pages of the kinds follow, so a snippet whose conditions reach no page still has samples.
     *
     * @param array<mixed> $body `{"kinds", "conditions", "query", "store"}`, `conditions` the expression builder's
     *     text
     * @return array{samples: list<Sample>}
     * @throws InvalidWizardRequestException
     */
    public function samples(array $body): array
    {
        $found = $this->pages->searchMeeting(
            $this->request->query($body),
            $this->request->storeId($body),
            $this->request->kinds($body),
            self::SAMPLE_LIMIT,
            $this->request->conditions($body)
        );

        return ['samples' => array_map(
            fn (PageMatchInterface $match): array => $this->presenter->sample($match),
            $found
        )];
    }

    /**
     * The snippets the snippet the query names can include; for a new snippet, every snippet
     *
     * @param array<mixed> $query `{"id"}`, left out for a new snippet
     * @return array{candidates: list<array{code: string, name: string, type: string, isBlock: bool,
     *     description: string, loops: bool}>}
     * @throws InvalidWizardRequestException
     * @throws MalformedLibraryTemplateException
     */
    public function includeCandidates(array $query): array
    {
        $id = $this->request->optionalId($query);
        try {
            $code = $id === null ? null : $this->snippets->get($id)->getCode();
        } catch (NoSuchEntityException $e) {
            throw new InvalidWizardRequestException('id', 'The snippet is not stored.', $e);
        }
        $candidates = [];
        foreach ($this->candidates->of($code) as $candidate) {
            $snippet = $candidate['snippet'];
            $candidates[] = [
                'code' => $snippet->getCode(),
                'name' => $snippet->getName(),
                'type' => $snippet->getType(),
                'isBlock' => $snippet->isBlock(),
                'description' => $candidate['description'] === '' ? '' : (string) __($candidate['description']),
                'loops' => $candidate['loops'],
            ];
        }

        return ['candidates' => $candidates];
    }
}
