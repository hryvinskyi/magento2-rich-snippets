<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Field;

use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;
use Hryvinskyi\RichSnippets\Api\FieldCatalogInterface;
use Hryvinskyi\RichSnippets\Api\FieldGroupInterface;

/**
 * The fields of the registered groups, in registration order, each path once: an earlier group's field shadows a
 * later group's field of the same path, and a field of a yielding group (the catalog attributes) gives way to a
 * field of the same path in any other group, wherever that group is listed
 *
 * The catalog is put together once per process, on first use.
 */
class FieldCatalog implements FieldCatalogInterface
{
    /**
     * @var array{
     *     fields: array<string, FieldInterface>,
     *     groups: array<string, FieldGroupInterface>,
     *     branches: array<string, true>
     * }|null
     */
    private ?array $catalog = null;

    /**
     * @param array<string,FieldGroupInterface> $groups by code, in the order the picker lists them
     * @param list<string> $yieldingGroups the codes of the groups whose fields give way to any other group's
     */
    public function __construct(
        private readonly array $groups = [],
        private readonly array $yieldingGroups = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getFields(): array
    {
        return array_values($this->catalog()['fields']);
    }

    /**
     * @inheritDoc
     */
    public function getField(string $path): ?FieldInterface
    {
        return $this->catalog()['fields'][$path] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function isBranch(string $path): bool
    {
        return isset($this->catalog()['branches'][$path]);
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $path, int $subjectId, int $storeId): mixed
    {
        $catalog = $this->catalog();
        $field = $catalog['fields'][$path] ?? null;

        return $field === null ? null : $catalog['groups'][$path]->resolve($field, $subjectId, $storeId);
    }

    /**
     * The fields, the group each belongs to and the paths above them, by path
     *
     * @return array{
     *     fields: array<string, FieldInterface>,
     *     groups: array<string, FieldGroupInterface>,
     *     branches: array<string, true>
     * }
     */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }
        $claimed = [];
        foreach ($this->groups as $code => $group) {
            if (!in_array((string) $code, $this->yieldingGroups, true)) {
                foreach ($group->getFields() as $field) {
                    $claimed[$field->getPath()] ??= (string) $code;
                }
            }
        }
        $fields = [];
        $groups = [];
        $branches = [];
        foreach ($this->groups as $code => $group) {
            foreach ($group->getFields() as $field) {
                $path = $field->getPath();
                if (isset($fields[$path]) || ($claimed[$path] ?? (string) $code) !== (string) $code) {
                    continue;
                }
                $fields[$path] = $field;
                $groups[$path] = $group;
                $segments = explode('.', $path);
                for ($length = count($segments) - 1; $length > 1; $length--) {
                    $branches[implode('.', array_slice($segments, 0, $length))] = true;
                }
            }
        }

        return $this->catalog = ['fields' => $fields, 'groups' => $groups, 'branches' => $branches];
    }
}
