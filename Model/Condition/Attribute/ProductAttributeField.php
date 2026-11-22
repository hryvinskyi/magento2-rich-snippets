<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Attribute;

use Hryvinskyi\RichSnippets\Api\ConditionFieldInterface;
use Hryvinskyi\RichSnippets\Api\ConditionSubjectInterface;
use Hryvinskyi\RichSnippets\Model\Condition\OperatorDescriptors;
use Hryvinskyi\UiFormElements\Api\AttributeOptionsProviderInterface;
use Hryvinskyi\UiFormElements\Api\AttributeValueSuggesterInterface;
use Hryvinskyi\UiFormElements\Api\ProductAttributeMetaProviderInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The "Product attribute" field: a condition on product pages that reads any offered product attribute
 *
 * The builder shows it as its attribute picker: a searchable list of the attributes, each described by the builder
 * package (input type, whether it has options, its operator set, its unit), with the option lists fetched on demand.
 * An attribute's operators are the builder package's set for its input type, or the text set when it names none, so
 * what the builder offers and what the parser accepts are one list.
 */
class ProductAttributeField implements ConditionFieldInterface, ResetAfterRequestInterface
{
    private const PICKER_TYPE = 'product_attribute_picker';
    private const VALUE_TYPE = 'product-attribute';

    /**
     * @var array<string, ConditionSubjectInterface> the subjects made so far, by attribute code
     */
    private array $subjects = [];

    /**
     * @param ConditionAttributes $attributes
     * @param ProductAttributeMetaProviderInterface $meta
     * @param AttributeOptionsProviderInterface $optionsProvider
     * @param AttributeValueSuggesterInterface $attributeValueSuggester
     * @param OperatorDescriptors $operators
     * @param ProductAttributeSubjectFactoryInterface $subjectFactory
     * @param string $pageKind
     * @param string $label in English; translated when it is read
     * @param list<string> $textOperators the codes of an attribute whose input type names no operator set
     */
    public function __construct(
        private readonly ConditionAttributes $attributes,
        private readonly ProductAttributeMetaProviderInterface $meta,
        private readonly AttributeOptionsProviderInterface $optionsProvider,
        private readonly AttributeValueSuggesterInterface $attributeValueSuggester,
        private readonly OperatorDescriptors $operators,
        private readonly ProductAttributeSubjectFactoryInterface $subjectFactory,
        private readonly string $pageKind = 'product',
        private readonly string $label = 'Product attribute',
        private readonly array $textOperators = ['==', '!=', '{}', '!{}', '()', '!()']
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPageKind(): string
    {
        return $this->pageKind;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string) __($this->label);
    }

    /**
     * @inheritDoc
     */
    public function subject(?string $attribute): ?ConditionSubjectInterface
    {
        if ($attribute === null || !$this->attributes->offers($attribute)) {
            return null;
        }
        if (!isset($this->subjects[$attribute])) {
            $label = $this->attributes->labels()[$attribute];
            $descriptor = $this->meta->getAttributes([$attribute => $label], [])[0] ?? [];
            $set = $descriptor['operatorSet'] ?? null;
            $this->subjects[$attribute] = $this->subjectFactory->create(
                $attribute,
                $label,
                (is_string($set) ? $this->operators->codesFor($set) : null) ?? $this->textOperators,
                $descriptor['unit'] ?? ''
            );
        }

        return $this->subjects[$attribute];
    }

    /**
     * @inheritDoc
     */
    public function builderField(string $type, int $storeId): array
    {
        return [
            'type' => self::PICKER_TYPE,
            'childType' => $type,
            'label' => $this->getLabel(),
            'valueType' => self::VALUE_TYPE,
            'operators' => $this->operators->describe($this->textOperators),
            'attributes' => $this->meta->getAttributes(
                $this->attributes->labels(),
                $this->attributeValueSuggester->getSuggestableCodes()
            ),
        ];
    }

    /**
     * @inheritDoc
     */
    public function options(?string $attribute, int $storeId): ?array
    {
        if ($attribute === null || !$this->attributes->offers($attribute)) {
            return null;
        }

        return $this->optionsProvider->getOptions($attribute);
    }

    /**
     * @inheritDoc
     */
    public function suggestions(?string $attribute, string $query): array
    {
        if ($attribute === null || !$this->attributes->offers($attribute)) {
            return [];
        }

        return $this->attributeValueSuggester->suggest($attribute, $query);
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->subjects = [];
    }
}
