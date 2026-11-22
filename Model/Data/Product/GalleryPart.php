<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Product;

use Hryvinskyi\RichSnippets\Model\Data\QueryRows;
use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Each product's gallery images the store view shows, in their order, as list items with the absolute `url` of the
 * file and its `label`; hidden images and videos are left out
 */
class GalleryPart implements EntityDataPartInterface
{
    private const GALLERY_ATTRIBUTE = 'media_gallery';
    private const IMAGE = 'image';
    private const PRODUCT_MEDIA = 'catalog/product/';

    /**
     * @param QueryRows $rows
     * @param Gallery $gallery
     * @param ProductAttributes $attributes
     * @param MetadataPool $metadataPool
     * @param StoreAddresses $addresses
     */
    public function __construct(
        private readonly QueryRows $rows,
        private readonly Gallery $gallery,
        private readonly ProductAttributes $attributes,
        private readonly MetadataPool $metadataPool,
        private readonly StoreAddresses $addresses
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $attributeId = $this->attributes->id(self::GALLERY_ATTRIBUTE);
        if ($ids === [] || $attributeId === 0) {
            return [];
        }
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $select = $this->gallery->createBatchBaseSelect($storeId, $attributeId);
        $select->join(
            ['product' => $metadata->getEntityTable()],
            'product.' . $metadata->getLinkField() . ' = entity.' . $metadata->getLinkField(),
            ['product_id' => 'product.entity_id']
        )->where('product.entity_id IN (?)', $ids);
        $entries = [];
        foreach ($this->rows->all($select) as $row) {
            if ((int) $row['disabled'] === 1 || (string) $row['media_type'] !== self::IMAGE) {
                continue;
            }
            $label = (string) ($row['label'] ?? '');
            $entries[(int) $row['product_id']][] = [
                'url' => $this->addresses->media(self::PRODUCT_MEDIA . ltrim((string) $row['file'], '/'), $storeId),
                'label' => $label !== '' ? $label : null,
            ];
        }

        return $entries;
    }
}
