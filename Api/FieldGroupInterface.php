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
 * A group of fields of the field picker, with the values of its fields
 *
 * Groups are registered in the field catalog's pool, so another module adds a group (a blog module: Post › Author)
 * without changing this one.
 *
 * @api
 */
interface FieldGroupInterface
{
    /**
     * The group's fields, in the order the picker lists them
     *
     * @return list<FieldInterface>
     */
    public function getFields(): array;

    /**
     * The value of one of the group's fields
     *
     * A field of a list item has no value of its own here (its list's items carry it): it yields null.
     *
     * @param FieldInterface $field
     * @param int $subjectId the entity the field's root stands for
     * @param int $storeId
     * @return mixed
     */
    public function resolve(FieldInterface $field, int $subjectId, int $storeId): mixed;
}
