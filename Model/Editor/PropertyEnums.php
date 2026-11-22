<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Editor;

/**
 * The values schema.org enumerates for a property, which the editor offers as fixed values
 *
 * The lists come from the container configuration, by property name, each value a schema.org enumeration member
 * written without its `https://schema.org/` prefix; another module adds a property or values in its own di.xml. A
 * malformed list is a configuration error, refused when the lists are built.
 */
class PropertyEnums
{
    private const MEMBER_PATTERN = '/\A[A-Z][A-Za-z0-9]*\z/';

    /**
     * @var array<string, list<string>>
     */
    private array $enums = [];

    /**
     * @param array<array-key,mixed> $enums the values of each property, by property name
     * @throws \InvalidArgumentException
     */
    public function __construct(array $enums = [])
    {
        foreach ($enums as $property => $values) {
            if (!is_array($values)) {
                throw new \InvalidArgumentException(sprintf('The values of "%s" are not a list.', $property));
            }
            $members = [];
            foreach ($values as $value) {
                if (!is_string($value) || preg_match(self::MEMBER_PATTERN, $value) !== 1) {
                    throw new \InvalidArgumentException(
                        sprintf('A value of "%s" is not a schema.org enumeration member.', $property)
                    );
                }
                $members[] = $value;
            }
            $this->enums[(string) $property] = array_values(array_unique($members));
        }
    }

    /**
     * Every property's values, by property name
     *
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->enums;
    }
}
