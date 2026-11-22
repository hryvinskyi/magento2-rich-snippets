<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Attribute;

use Hryvinskyi\RichSnippets\Api\ConditionSubjectInterface;
use Hryvinskyi\RichSnippets\Api\ConditionValueSqlInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\EavStoreValueJoin;
use Hryvinskyi\RichSnippets\Model\Catalog\OptionLabels;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;
use Hryvinskyi\RichSnippets\Model\Condition\Sql\ConditionValueSqlFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Data\AttributeEntries;
use Magento\Framework\App\ResourceConnection;

/**
 * One product attribute as what a condition reads of a product page
 *
 * The product's value is read from the attribute values its page's data source loads, the attribute's variable path
 * named beforehand so it comes in the same batch as the templates' attributes. The value in the store view stands,
 * the default value where the store view has none. A multiselect has one value per option it holds; a date or a
 * moment is its day (`Y-m-d`), so "is" means that day and the orderings compare days.
 */
class ProductAttributeSubject implements ConditionSubjectInterface
{
    private const PRODUCT_KIND = 'product';
    private const PRODUCT_TABLE = 'catalog_product_entity';
    private const OPTION_TABLE = 'eav_attribute_option';
    private const ATTRIBUTES_PART = 'attributes';
    private const MULTISELECT = 'multiselect';
    private const OPTION_INPUTS = ['select', 'multiselect', 'boolean'];
    private const DAY_INPUTS = ['date', 'datetime'];
    private const DAY_LENGTH = 10;
    private const LIST_SEPARATOR = ',';

    /**
     * @param ProductAttributes $attributes
     * @param EntityDataSourceInterface $products the product pages' data source
     * @param AttributeEntries $entries
     * @param EavStoreValueJoin $join the product attributes' value join
     * @param OptionLabels $optionLabels
     * @param ResourceConnection $resource
     * @param ConditionValueSqlFactoryInterface $sql
     * @param string $code
     * @param string $label
     * @param list<string> $operators the operators' codes
     * @param string $unit
     */
    public function __construct(
        private readonly ProductAttributes $attributes,
        private readonly EntityDataSourceInterface $products,
        private readonly AttributeEntries $entries,
        private readonly EavStoreValueJoin $join,
        private readonly OptionLabels $optionLabels,
        private readonly ResourceConnection $resource,
        private readonly ConditionValueSqlFactoryInterface $sql,
        private readonly string $code,
        private readonly string $label,
        private readonly array $operators,
        private readonly string $unit = ''
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getPhrase(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getOperators(): array
    {
        return $this->operators;
    }

    /**
     * @inheritDoc
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * @inheritDoc
     */
    public function namesOwnValue(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function pageValues(PageEntityInterface $page): array
    {
        $entityId = $page->getEntityId();
        if ($entityId === null || $page->getKind() !== self::PRODUCT_KIND) {
            return [];
        }
        $entry = $this->entries->entry(
            $this->products,
            self::ATTRIBUTES_PART,
            self::PRODUCT_KIND,
            $this->code,
            $entityId,
            $page->getStoreId()
        );
        $value = $entry['values'][$this->code] ?? null;
        if (!is_scalar($value) || (string) $value === '') {
            return [];
        }
        $input = $this->attributes->input($this->code);
        if ($input === self::MULTISELECT) {
            return array_values(array_filter(
                array_map('trim', explode(self::LIST_SEPARATOR, (string) $value)),
                static fn (string $option): bool => $option !== ''
            ));
        }

        return [in_array($input, self::DAY_INPUTS, true)
            ? substr((string) $value, 0, self::DAY_LENGTH)
            : (string) $value];
    }

    /**
     * @inheritDoc
     */
    public function valueLabel(string $value, int $storeId): ?string
    {
        if (!in_array($this->attributes->input($this->code), self::OPTION_INPUTS, true)) {
            return null;
        }
        if ($this->attributes->optionsInTables($this->code)) {
            return ctype_digit($value)
                ? $this->optionLabels->labels([(int) $value], $storeId)[(int) $value] ?? null
                : null;
        }

        return $this->attributes->sourceOptionLabels($this->code, $storeId)[$value] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function valueSql(string $entityIdExpression, int $storeId): ConditionValueSqlInterface
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['rs_product' => $this->resource->getTableName(self::PRODUCT_TABLE)], ['rs_product.entity_id'])
            ->where('rs_product.entity_id = ' . $entityIdExpression);
        $value = $this->attributes->isStatic($this->code)
            ? 'rs_product.' . $connection->quoteIdentifier($this->code)
            : $this->join->join($select, 'rs_product.entity_id', $this->code, $storeId, 'rs_value');
        $input = $this->attributes->input($this->code);
        if ($input === self::MULTISELECT) {
            $select->join(
                ['rs_option' => $this->resource->getTableName(self::OPTION_TABLE)],
                sprintf(
                    'rs_option.attribute_id = %d AND FIND_IN_SET(rs_option.option_id, %s)',
                    $this->attributes->id($this->code),
                    $value
                ),
                []
            );
            $value = 'rs_option.option_id';
        }
        if (in_array($input, self::DAY_INPUTS, true)) {
            $value = sprintf('LEFT(%s, %d)', $value, self::DAY_LENGTH);
        }

        return $this->sql->create($select, $value);
    }

    /**
     * @inheritDoc
     */
    public function getVariablePaths(): array
    {
        return [self::PRODUCT_KIND . '.' . $this->code];
    }
}
