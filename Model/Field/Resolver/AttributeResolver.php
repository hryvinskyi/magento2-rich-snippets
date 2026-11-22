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

/**
 * The stored text of the attribute a field reads, from an entity source's attribute part; empty text is nothing
 */
class AttributeResolver implements FieldResolverInterface
{
    /**
     * @param EntityDataSourceInterface $source
     * @param AttributeEntries $entries
     * @param string $root the root whose paths name the attributes (`category`)
     * @param string $part the source's part holding the attribute values
     */
    public function __construct(
        private readonly EntityDataSourceInterface $source,
        private readonly AttributeEntries $entries,
        private readonly string $root,
        private readonly string $part = 'attributes'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(FieldInterface $field, int $subjectId, int $storeId): mixed
    {
        $code = $field->getAttributeCode();
        $entry = $code === null
            ? null
            : $this->entries->entry($this->source, $this->part, $this->root, $code, $subjectId, $storeId);
        $value = $code === null ? null : ($entry['values'][$code] ?? null);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
