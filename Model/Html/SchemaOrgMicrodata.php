<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Html;

/**
 * What schema.org microdata is in an HTML page: its attributes, and when a type names schema.org
 *
 * An item is an element with `itemscope`; its type is its `itemtype`, a space-separated list of type addresses; a
 * property is an element with `itemprop`, whose value may name several properties at once. Microdata counts as
 * schema.org when the type names the vocabulary, over http or https.
 */
class SchemaOrgMicrodata
{
    public const SCOPE_ATTRIBUTE = 'itemscope';
    public const PROPERTY_ATTRIBUTE = 'itemprop';
    public const TYPE_ATTRIBUTE = 'itemtype';
    public const ID_ATTRIBUTE = 'itemid';
    public const REFERENCE_ATTRIBUTE = 'itemref';

    private const VOCABULARY = 'schema.org';

    /**
     * Whether a type attribute's value names schema.org
     *
     * @param string $itemtype the value, quotes included or not
     * @return bool
     */
    public function namesSchemaOrg(string $itemtype): bool
    {
        return stripos($itemtype, self::VOCABULARY) !== false;
    }

    /**
     * The first schema.org type a type attribute names, without the vocabulary's address (`Product`)
     *
     * @param string $itemtype
     * @return string empty when it names none
     */
    public function typeName(string $itemtype): string
    {
        return $this->typeNames($itemtype)[0] ?? '';
    }

    /**
     * Every schema.org type a type attribute names, in its order, without the vocabulary's address
     *
     * @param string $itemtype
     * @return list<string> empty when it names none
     */
    public function typeNames(string $itemtype): array
    {
        $names = [];
        foreach (preg_split('~\s+~', trim($itemtype)) ?: [] as $address) {
            if ($this->namesSchemaOrg($address)) {
                $path = trim((string) preg_replace('~[?#].*\z~s', '', $address), '/');
                $names[] = (string) preg_replace('~\A.*/~s', '', $path);
            }
        }

        return $names;
    }

    /**
     * The property names an `itemprop` value lists
     *
     * @param string $itemprop
     * @return list<string>
     */
    public function propertyNames(string $itemprop): array
    {
        return array_values(array_filter(
            preg_split('~\s+~', trim($itemprop)) ?: [],
            static fn (string $name): bool => $name !== ''
        ));
    }
}
