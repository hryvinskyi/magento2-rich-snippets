<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field\Group;

use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;
use Hryvinskyi\RichSnippets\Api\FieldFactoryInterface;
use Hryvinskyi\RichSnippets\Api\FieldGroupInterface;
use Hryvinskyi\RichSnippets\Api\FieldResolverInterface;
use Hryvinskyi\RichSnippets\Model\Catalog\ProductAttributes;

/**
 * Every attribute of the product entity as a field `product.<code>`, labelled by the attribute's admin label
 *
 * The list follows the catalog: an attribute added in the admin is a field at once. A built-in field listed earlier
 * in the catalog shadows the attribute of the same code.
 */
class ProductAttributeFieldGroup implements FieldGroupInterface
{
    private const ROOT = 'product';
    private const PAGE_KINDS = ['product'];

    /**
     * @var list<FieldInterface>|null
     */
    private ?array $fields = null;

    /**
     * @param ProductAttributes $attributes
     * @param FieldFactoryInterface $fieldFactory
     * @param FieldResolverInterface $resolver the resolver of an attribute's value
     * @param string $code
     * @param string $label
     */
    public function __construct(
        private readonly ProductAttributes $attributes,
        private readonly FieldFactoryInterface $fieldFactory,
        private readonly FieldResolverInterface $resolver,
        private readonly string $code,
        private readonly string $label
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getFields(): array
    {
        if ($this->fields === null) {
            $this->fields = [];
            foreach ($this->attributes->codes() as $attribute) {
                $this->fields[] = $this->fieldFactory->create(
                    self::ROOT . '.' . $attribute,
                    $this->attributes->label($attribute),
                    $this->code,
                    $this->label,
                    $attribute,
                    self::PAGE_KINDS
                );
            }
        }

        return $this->fields;
    }

    /**
     * @inheritDoc
     */
    public function resolve(FieldInterface $field, int $subjectId, int $storeId): mixed
    {
        return $this->resolver->resolve($field, $subjectId, $storeId);
    }
}
