<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\ConditionBuilderConfigInterface;
use Hryvinskyi\RichSnippets\Api\ConditionFieldInterface;
use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\PlacementCounterInterface;
use Hryvinskyi\RichSnippets\Model\Placement\PlacementSentence;
use Hryvinskyi\UiFormElements\Api\ExpressionBuilderConfigInterface;

/**
 * Answers what the editor's Pages section reads: the expression builder's configuration, the option lists and value
 * suggestions the builder asks for, and the pages a placement reaches with the placement in words
 *
 * Each read is for one store view: the one the request names, else the default store view. An option list is
 * answered only for what a field offers, so the endpoint cannot read the options of an attribute the builder never
 * shows.
 *
 * @phpstan-import-type Words from PlacementSentence
 */
class ConditionCatalogs
{
    private const FIELD = 'field';

    /**
     * @param EditorRequest $request
     * @param ConditionBuilderConfigInterface $builder
     * @param ConditionFieldPoolInterface $fields
     * @param PlacementCounterInterface $counter
     * @param PlacementSentence $sentence
     */
    public function __construct(
        private readonly EditorRequest $request,
        private readonly ConditionBuilderConfigInterface $builder,
        private readonly ConditionFieldPoolInterface $fields,
        private readonly PlacementCounterInterface $counter,
        private readonly PlacementSentence $sentence
    ) {
    }

    /**
     * The builder's configuration for some kinds of page: `{"config": {...}}`
     *
     * Without value suggestions the builder's suggestion address is empty, and the builder asks for none.
     *
     * @param array<mixed> $query `{"kinds", "store"}`; every kind when it names none
     * @param bool $suggest whether the builder offers value suggestions
     * @return array{config: array<string, mixed>}
     * @throws InvalidWizardRequestException
     */
    public function builderConfig(array $query, bool $suggest): array
    {
        $config = $this->builder->forKinds($this->request->kinds($query), $this->request->storeId($query));
        if (!$suggest) {
            $config[ExpressionBuilderConfigInterface::SUGGEST_URL] = '';
        }

        return ['config' => $config];
    }

    /**
     * The options of a field's attribute, `{"options": [...]}`, or with a query the values matching it,
     * `{"items": [...]}`
     *
     * The field may be left out when an attribute is named: the first field that offers the attribute answers.
     *
     * @param array<mixed> $query `{"field", "attribute", "q", "store"}`
     * @return array{options: list<array<string, string|int>>}|array{items: list<array{value: string, label: string}>}
     * @throws InvalidWizardRequestException when no field offers the attribute
     */
    public function conditionOptions(array $query): array
    {
        $attribute = $this->request->parameter($query, EditorRequest::ATTRIBUTE);
        $attribute = $attribute === '' ? null : $attribute;
        $storeId = $this->request->storeId($query);
        $search = trim($this->request->parameter($query, EditorRequest::SUGGEST_QUERY));
        $code = $this->request->parameter($query, self::FIELD);
        if ($code === '' && $attribute === null) {
            throw new InvalidWizardRequestException(EditorRequest::ATTRIBUTE, 'The request names no attribute.');
        }
        foreach ($this->candidates($code) as $field) {
            if ($search !== '' && $field->subject($attribute) !== null) {
                return ['items' => $field->suggestions($attribute, $search)];
            }
            $options = $search === '' ? $field->options($attribute, $storeId) : null;
            if ($options !== null) {
                return ['options' => $options];
            }
        }
        throw new InvalidWizardRequestException(EditorRequest::ATTRIBUTE, 'No condition field offers this attribute.');
    }

    /**
     * How many of the store view's pages of each picked kind meet the conditions, with the placement in words
     *
     * `{"kinds": {kind: {"matching", "total"}}, "matching", "total", "sentence", "count", "countLine", "asked"}`; a
     * kind whose pages cannot be listed is not among the kinds.
     *
     * @param array<mixed> $body `{"pageKinds", "conditions", "storeIds", "store"}`
     * @return array{kinds: array<string, array{matching: int, total: int}>, matching: int, total: int,
     *     sentence: string, count: string, countLine: string, asked: string|null}
     * @throws InvalidWizardRequestException
     */
    public function placementCount(array $body): array
    {
        $placement = $this->request->placement($body);
        $storeId = $this->request->storeId($body);
        $counts = $this->counter->count($placement['kinds'], $placement['conditions'], $storeId);
        $kinds = [];
        $matching = 0;
        $total = 0;
        foreach ($counts as $kind => $count) {
            $kinds[$kind] = ['matching' => $count->getMatching(), 'total' => $count->getTotal()];
            $matching += $count->getMatching();
            $total += $count->getTotal();
        }
        $words = $this->sentence->describe(
            $placement['kinds'],
            $placement['conditions'],
            $placement['storeIds'],
            $counts,
            $storeId
        );

        return ['kinds' => $kinds, 'matching' => $matching, 'total' => $total] + $words;
    }

    /**
     * The fields that may answer: the one named, or every field when none is
     *
     * @param string $code
     * @return list<ConditionFieldInterface>
     * @throws InvalidWizardRequestException when the code names no field
     */
    private function candidates(string $code): array
    {
        if ($code === '') {
            return array_map(
                fn (string $field): ConditionFieldInterface => $this->fields->get($field),
                $this->fields->getCodes()
            );
        }
        if (!$this->fields->has($code)) {
            throw new InvalidWizardRequestException(self::FIELD, 'No condition field has the code.');
        }

        return [$this->fields->get($code)];
    }
}
