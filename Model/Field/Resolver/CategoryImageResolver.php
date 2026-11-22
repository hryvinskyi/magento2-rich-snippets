<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Resolver;

use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;
use Hryvinskyi\RichSnippets\Api\EntityDataSourceInterface;
use Hryvinskyi\RichSnippets\Api\FieldResolverInterface;
use Hryvinskyi\RichSnippets\Model\Data\AttributeEntries;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;

/**
 * A category's image as an absolute address: a value stored as a path under the web root (`/media/…`) is read from
 * there, a bare file name from the category image directory
 */
class CategoryImageResolver implements FieldResolverInterface
{
    private const CATEGORY_MEDIA = 'catalog/category/';
    private const ROOT = 'category';

    /**
     * @param EntityDataSourceInterface $categories
     * @param StoreAddresses $addresses
     * @param AttributeEntries $entries
     * @param string $part the category source's part holding the attribute values
     * @param string $code the image attribute's code
     */
    public function __construct(
        private readonly EntityDataSourceInterface $categories,
        private readonly StoreAddresses $addresses,
        private readonly AttributeEntries $entries,
        private readonly string $part = 'attributes',
        private readonly string $code = 'image'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(FieldInterface $field, int $subjectId, int $storeId): mixed
    {
        $entry = $this->entries->entry($this->categories, $this->part, self::ROOT, $this->code, $subjectId, $storeId);
        $image = $entry['values'][$this->code] ?? null;
        if (!is_string($image) || $image === '') {
            return null;
        }
        if (str_starts_with($image, '/')) {
            return $this->addresses->web($image, $storeId);
        }

        return $this->addresses->media(
            str_contains($image, '/') ? $image : self::CATEGORY_MEDIA . $image,
            $storeId
        );
    }
}
