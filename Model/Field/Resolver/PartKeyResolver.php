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

/**
 * A field whose value an entity source's part holds as it is, under a key: the one configured (dots step into
 * nested values: `values.type_id`), else the field's path below its root (`final_price` for
 * `product.final_price`, `address.city` for `store.address.city`)
 */
class PartKeyResolver implements FieldResolverInterface
{
    /**
     * @param EntityDataSourceInterface $source
     * @param string $part
     * @param string $key empty: the field's path below its root
     */
    public function __construct(
        private readonly EntityDataSourceInterface $source,
        private readonly string $part,
        private readonly string $key = ''
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(FieldInterface $field, int $subjectId, int $storeId): mixed
    {
        $key = $this->key !== '' ? $this->key : $this->belowRoot($field->getPath());
        $value = $this->source->get($subjectId, $storeId, $this->part);
        if (is_array($value) && array_key_exists($key, $value)) {
            return $value[$key] === '' ? null : $value[$key];
        }
        foreach (explode('.', $key) as $step) {
            if (!is_array($value)) {
                return null;
            }
            $value = $value[$step] ?? null;
        }

        return $value === '' ? null : $value;
    }

    /**
     * A path without its root
     *
     * @param string $path
     * @return string
     */
    private function belowRoot(string $path): string
    {
        $dot = strpos($path, '.');

        return $dot === false ? $path : substr($path, $dot + 1);
    }
}
