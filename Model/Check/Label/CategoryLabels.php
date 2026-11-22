<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Label;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

/**
 * Names categories by their name in the store view
 */
class CategoryLabels implements PageLabelsInterface
{
    /**
     * @param CollectionFactory $categories
     */
    public function __construct(private readonly CollectionFactory $categories)
    {
    }

    /**
     * @inheritDoc
     */
    public function labels(array $ids, int $storeId): array
    {
        if ($ids === []) {
            return [];
        }
        $collection = $this->categories->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addIdFilter($ids);
        $labels = [];
        foreach ($collection->getItems() as $category) {
            $id = $category->getData('entity_id');
            $name = $category->getData('name');
            if (!is_numeric($id)) {
                continue;
            }
            $labels[(int) $id] = [
                'label' => is_string($name) ? $name : '',
                'sku' => null,
                'group' => null,
            ];
        }

        return $labels;
    }
}
