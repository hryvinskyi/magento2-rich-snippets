<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront;

use Hryvinskyi\RichSnippets\Model\Html\SchemaOrgMicrodata;

/**
 * Takes the schema.org microdata items of given types out of an HTML page, and leaves every other item
 *
 * An item goes when its `itemtype` names one of the types (any of them, when it lists several); with it go its own
 * microdata attributes (`itemscope`, `itemtype`, `itemprop`, `itemid`, `itemref`), the `itemprop` of every element
 * inside it, and every item inside it that is one of its properties. An item inside it that is no property of it
 * (no `itemprop`) is a separate item and stays unless its own type is one of the given. Text, other attributes, and
 * the content of scripts, styles, comments, titles and text areas stay as they are.
 *
 * One pass over the page reads its tags and keeps the open elements on a stack: an end tag closes the nearest open
 * element of its name and whatever is still open inside it, an end tag with no such element is ignored, and empty
 * elements (`img`, `meta`, `br`…) never stay open. A tag that cannot be read as one is left as written; an item that
 * is never closed lasts to the end of the page.
 */
class MicrodataRemover
{
    private const TOKENS = '~<!--(?:[^-]++|-(?!->))*+(?:-->)?'
        . '|<(?<raw>script|style|textarea|title)\b[^>]*+>(?:[^<]++|<(?!/(?P=raw)))*+(?:</(?P=raw)\s*+>)?'
        . '|</(?<end>[a-zA-Z][^\s/>]*+)[^>]*+>'
        . '|<(?<start>[a-zA-Z][^\s/>]*+)'
        . '(?:\s*+(?:[^\s"\'/>=]++(?:\s*+=\s*+(?:"[^"]*+"|\'[^\']*+\'|[^\s"\'>]++))?|/))*+\s*+>~i';
    private const ATTRIBUTES = '~\s*+([^\s"\'/>=]++)(?:\s*+=\s*+("[^"]*+"|\'[^\']*+\'|[^\s"\'>]++))?~';
    private const MICRODATA_PREFIX = 'item';
    private const QUOTES = '"\'';
    private const EMPTY_ELEMENTS = [
        'area' => true, 'base' => true, 'br' => true, 'col' => true, 'embed' => true, 'hr' => true, 'img' => true,
        'input' => true, 'link' => true, 'meta' => true, 'param' => true, 'source' => true, 'track' => true,
        'wbr' => true,
    ];
    private const ITEM_ATTRIBUTES = [
        SchemaOrgMicrodata::SCOPE_ATTRIBUTE => true,
        SchemaOrgMicrodata::TYPE_ATTRIBUTE => true,
        SchemaOrgMicrodata::PROPERTY_ATTRIBUTE => true,
        SchemaOrgMicrodata::ID_ATTRIBUTE => true,
        SchemaOrgMicrodata::REFERENCE_ATTRIBUTE => true,
    ];
    private const PROPERTY_ATTRIBUTES = [SchemaOrgMicrodata::PROPERTY_ATTRIBUTE => true];

    /**
     * @param SchemaOrgMicrodata $microdata
     */
    public function __construct(private readonly SchemaOrgMicrodata $microdata)
    {
    }

    /**
     * The page without the microdata items of the given schema.org types
     *
     * @param string $html
     * @param list<string> $types schema.org type names (`Product`); none leaves the page as it is
     * @return string
     */
    public function strip(string $html, array $types): string
    {
        $replaced = array_fill_keys($types, true);
        if (!$this->mayHoldAnItemOf($html, $replaced)) {
            return $html;
        }
        $open = [];

        return preg_replace_callback(
            self::TOKENS,
            function (array $token) use (&$open, $replaced): string {
                $start = $token['start'] ?? '';
                if ($start !== '') {
                    return $this->startTag($token[0], strtolower($start), $open, $replaced);
                }
                $end = $token['end'] ?? '';
                if ($end !== '') {
                    $this->close(strtolower($end), $open);
                }

                return $token[0];
            },
            $html
        ) ?? $html;
    }

    /**
     * Whether the page has items at all and names one of the types somewhere: without both, nothing can go
     *
     * @param string $html
     * @param array<string,true> $replaced
     * @return bool
     */
    private function mayHoldAnItemOf(string $html, array $replaced): bool
    {
        if ($replaced === [] || stripos($html, SchemaOrgMicrodata::SCOPE_ATTRIBUTE) === false) {
            return false;
        }
        foreach (array_keys($replaced) as $type) {
            if (str_contains($html, (string) $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A start tag without the microdata that goes, after putting its element on the stack of open elements
     *
     * @param string $tag the tag as written
     * @param string $name its element name, in lower case
     * @param list<array{string,bool}> $open the open elements: name, and whether properties inside it go
     * @param array<string,true> $replaced
     * @return string
     * @param-out list<array{string,bool}> $open
     */
    private function startTag(string $tag, string $name, array &$open, array $replaced): string
    {
        $inRemovedItem = $open !== [] && $open[array_key_last($open)][1];
        $attributes = substr($tag, strlen($name) + 1);
        $microdata = stripos($attributes, self::MICRODATA_PREFIX) === false ? [] : $this->microdataOf($attributes);
        $isItem = isset($microdata[SchemaOrgMicrodata::SCOPE_ATTRIBUTE]);
        $isProperty = isset($microdata[SchemaOrgMicrodata::PROPERTY_ATTRIBUTE]);
        $itemGoes = $isItem && (($isProperty && $inRemovedItem)
            || $this->namesAReplacedType($microdata[SchemaOrgMicrodata::TYPE_ATTRIBUTE] ?? '', $replaced));
        if (!isset(self::EMPTY_ELEMENTS[$name])) {
            $open[] = [$name, $isItem ? $itemGoes : $inRemovedItem];
        }
        $dropped = $itemGoes ? self::ITEM_ATTRIBUTES : ($isProperty && $inRemovedItem ? self::PROPERTY_ATTRIBUTES : []);
        if ($dropped === []) {
            return $tag;
        }

        return substr($tag, 0, strlen($name) + 1) . (preg_replace_callback(
            self::ATTRIBUTES,
            static fn (array $attribute): string => isset($dropped[strtolower($attribute[1])]) ? '' : $attribute[0],
            $attributes
        ) ?? $attributes);
    }

    /**
     * Close the nearest open element of a name and every element still open inside it; nothing when none is open
     *
     * @param string $name in lower case
     * @param list<array{string,bool}> $open
     * @return void
     * @param-out list<array{string,bool}> $open
     */
    private function close(string $name, array &$open): void
    {
        for ($index = count($open) - 1; $index >= 0; $index--) {
            if ($open[$index][0] === $name) {
                $open = array_slice($open, 0, $index);

                return;
            }
        }
    }

    /**
     * The microdata attributes of a start tag, by lower-case name, each value as written; the first of a name counts
     *
     * @param string $attributes the tag after its name
     * @return array<string, string>
     */
    private function microdataOf(string $attributes): array
    {
        preg_match_all(self::ATTRIBUTES, $attributes, $found, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        $microdata = [];
        foreach ($found as $attribute) {
            $name = strtolower((string) $attribute[1]);
            if (isset(self::ITEM_ATTRIBUTES[$name]) && !isset($microdata[$name])) {
                $microdata[$name] = (string) ($attribute[2] ?? '');
            }
        }

        return $microdata;
    }

    /**
     * Whether a type attribute names one of the replaced schema.org types
     *
     * @param string $itemtype the value as written, quotes included
     * @param array<string,true> $replaced
     * @return bool
     */
    private function namesAReplacedType(string $itemtype, array $replaced): bool
    {
        foreach ($this->microdata->typeNames(trim($itemtype, self::QUOTES)) as $type) {
            if (isset($replaced[$type])) {
                return true;
            }
        }

        return false;
    }
}
