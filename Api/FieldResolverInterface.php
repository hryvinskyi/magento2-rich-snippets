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
 * Finds the value of a field for the entity its root stands for
 *
 * A value is an array, a scalar or null, in the form the field promises (a price as a string at the currency's
 * precision, a date as ISO 8601, an image as an absolute URL …). Null means the entity has no value.
 *
 * @api
 */
interface FieldResolverInterface
{
    /**
     * The field's value
     *
     * @param FieldInterface $field
     * @param int $subjectId the entity the field's root stands for (the product of a product page, the store view)
     * @param int $storeId
     * @return mixed
     */
    public function resolve(FieldInterface $field, int $subjectId, int $storeId): mixed;
}
