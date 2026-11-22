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

/**
 * A group whose fields are listed in its configuration, each with its path, label, the attribute it reads (if any)
 * and the resolver of its value
 *
 * A field without a resolver is a field of a list item: its list's items carry the value. A definition without a
 * path or a label is a configuration error and refused.
 */
class ConfiguredFieldGroup implements FieldGroupInterface
{
    /**
     * @var array{fields: list<FieldInterface>, resolvers: array<string, FieldResolverInterface>}|null
     */
    private ?array $built = null;

    /**
     * @param FieldFactoryInterface $fieldFactory
     * @param string $code
     * @param string $label
     * @param array<array-key,string> $pageKinds the kinds of page on which the group's fields have a value; none:
     *     every kind. Keyed as the container configuration writes them; the fields list them in order, unkeyed.
     * @param array<string,array<string,mixed>> $fields definitions by name: `path`, `label`, optional `attribute`
     *     and `resolver`
     */
    public function __construct(
        private readonly FieldFactoryInterface $fieldFactory,
        private readonly string $code,
        private readonly string $label,
        private readonly array $pageKinds = [],
        private readonly array $fields = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getFields(): array
    {
        return $this->built()['fields'];
    }

    /**
     * @inheritDoc
     */
    public function resolve(FieldInterface $field, int $subjectId, int $storeId): mixed
    {
        $resolver = $this->built()['resolvers'][$field->getPath()] ?? null;

        return $resolver?->resolve($field, $subjectId, $storeId);
    }

    /**
     * The fields and their resolvers, built from the definitions once
     *
     * @return array{fields: list<FieldInterface>, resolvers: array<string, FieldResolverInterface>}
     * @throws \LogicException when a definition has no path or label
     */
    private function built(): array
    {
        if ($this->built !== null) {
            return $this->built;
        }
        $fields = [];
        $resolvers = [];
        foreach ($this->fields as $name => $definition) {
            $path = $definition['path'] ?? null;
            $label = $definition['label'] ?? null;
            if (!is_string($path) || $path === '' || !is_scalar($label) || (string) $label === '') {
                throw new \LogicException(
                    sprintf('The field "%s" of group "%s" needs a path and a label.', $name, $this->code)
                );
            }
            $attribute = $definition['attribute'] ?? null;
            $fields[] = $this->fieldFactory->create(
                $path,
                (string) $label,
                $this->code,
                $this->label,
                is_string($attribute) && $attribute !== '' ? $attribute : null,
                array_values($this->pageKinds)
            );
            $resolver = $definition['resolver'] ?? null;
            if ($resolver instanceof FieldResolverInterface) {
                $resolvers[$path] = $resolver;
            }
        }

        return $this->built = ['fields' => $fields, 'resolvers' => $resolvers];
    }
}
