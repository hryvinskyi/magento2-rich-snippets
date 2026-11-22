<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Attribute;

use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The product attributes a condition on product pages may read, with their labels, in the order of their labels
 *
 * Every attribute that holds a value of its own: one with a value table, or one stored as a column of the product
 * table. An attribute without either (one a separate table or a module's own storage serves), or one listed as
 * excluded, is not offered. The list is worked out once per request.
 */
class ConditionAttributes implements ResetAfterRequestInterface
{
    private const PRODUCT_TABLE = 'catalog_product_entity';

    /**
     * @var array<string, string>|null by code
     */
    private ?array $labels = null;

    /**
     * @param ProductAttributes $attributes
     * @param ResourceConnection $resource
     * @param array<array-key,mixed> $excludedCodes codes never offered, by any key
     * @param array<array-key,mixed> $excludedInputs input types never offered, by any key
     */
    public function __construct(
        private readonly ProductAttributes $attributes,
        private readonly ResourceConnection $resource,
        private readonly array $excludedCodes = [],
        private readonly array $excludedInputs = []
    ) {
    }

    /**
     * The offered attributes' labels, by code
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        if ($this->labels !== null) {
            return $this->labels;
        }
        $columns = $this->resource->getConnection()->describeTable(
            $this->resource->getTableName(self::PRODUCT_TABLE)
        );
        $this->labels = [];
        foreach ($this->attributes->codes() as $code) {
            if (in_array($code, $this->excludedCodes, true)
                || in_array($this->attributes->input($code), $this->excludedInputs, true)
                || ($this->attributes->isStatic($code) && !isset($columns[$code]))
            ) {
                continue;
            }
            $this->labels[$code] = $this->attributes->label($code);
        }

        return $this->labels;
    }

    /**
     * Whether an attribute is offered
     *
     * @param string $code
     * @return bool
     */
    public function offers(string $code): bool
    {
        return isset($this->labels()[$code]);
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->labels = null;
    }
}
