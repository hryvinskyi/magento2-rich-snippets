<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;

/**
 * Every field a template can read: the registered groups' fields, each path once
 *
 * A path listed by an earlier group shadows the same path in a later one, so a built-in field wins over the catalog
 * attribute of the same code (`product.image` is the main image).
 *
 * @api
 */
interface FieldCatalogInterface
{
    /**
     * Every field, group by group
     *
     * @return list<FieldInterface>
     */
    public function getFields(): array;

    /**
     * The field with a path
     *
     * @param string $path
     * @return FieldInterface|null null when no field has this path
     */
    public function getField(string $path): ?FieldInterface;

    /**
     * Whether some field's path continues below this one (`store.address` above `store.address.city`)
     *
     * @param string $path
     * @return bool
     */
    public function isBranch(string $path): bool;

    /**
     * The value of the field with a path
     *
     * @param string $path
     * @param int $subjectId the entity the field's root stands for
     * @param int $storeId
     * @return mixed null when no field has this path or the entity has no value
     */
    public function resolve(string $path, int $subjectId, int $storeId): mixed;
}
