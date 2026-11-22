<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Builder;

use Hryvinskyi\RichSnippets\Api\ConditionBuilderConfigInterface;
use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Model\Condition\OperatorDescriptors;
use Hryvinskyi\UiFormElements\Api\AttributeValueSuggesterInterface;
use Hryvinskyi\UiFormElements\Api\ExpressionBuilderConfigInterface;
use Magento\Backend\Model\UrlInterface;

/**
 * The expression builder's configuration for the picked kinds of page, from the condition field pool
 *
 * Option lists of product attributes and value suggestions come from the module's own condition options endpoint,
 * which answers only for what a field offers. The builder counts no matches itself: the editor asks for the
 * placement's count, kind by kind.
 */
class ConditionBuilderConfig implements ConditionBuilderConfigInterface
{
    /**
     * @param PageKindPoolInterface $kinds
     * @param ConditionFieldPoolInterface $fields
     * @param OperatorDescriptors $operators
     * @param AttributeValueSuggesterInterface $attributeValueSuggester
     * @param UrlInterface $urls
     * @param string $optionsRoute the route path of the condition options endpoint
     */
    public function __construct(
        private readonly PageKindPoolInterface $kinds,
        private readonly ConditionFieldPoolInterface $fields,
        private readonly OperatorDescriptors $operators,
        private readonly AttributeValueSuggesterInterface $attributeValueSuggester,
        private readonly UrlInterface $urls,
        private readonly string $optionsRoute = 'hryvinskyi_richsnippets/snippets/conditionOptions'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forKinds(array $kinds, int $storeId): array
    {
        foreach ($kinds as $kind) {
            if (!$this->kinds->has($kind)) {
                throw new \InvalidArgumentException(sprintf('No kind of page has the code "%s".', $kind));
            }
        }
        $fields = [];
        foreach (array_intersect($this->kinds->getCodes(), $kinds) as $kind) {
            foreach ($this->fields->forKind($kind) as $code => $field) {
                $fields[] = $field->builderField($code, $storeId);
            }
        }
        $options = $this->urls->getUrl($this->optionsRoute);

        return [
            ExpressionBuilderConfigInterface::COMBINE_TYPE => ConditionTreeInterface::GROUP_TYPE,
            ExpressionBuilderConfigInterface::FIELDS => $fields,
            ExpressionBuilderConfigInterface::OPERATOR_SETS => $this->operators->sets(),
            ExpressionBuilderConfigInterface::EMPTY_STATE_MESSAGE
                => (string) __('No conditions: it is output on every one of them.'),
            ExpressionBuilderConfigInterface::OPTIONS_URL => $options,
            ExpressionBuilderConfigInterface::SUGGEST_URL => $options,
            ExpressionBuilderConfigInterface::SUGGEST_MIN_QUERY => $this->attributeValueSuggester->getMinQueryLength(),
        ];
    }
}
