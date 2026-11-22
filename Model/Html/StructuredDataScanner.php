<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Html;

use Hryvinskyi\RichSnippets\Api\Data\JsonLdBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\JsonLdBlockInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\MicrodataItemInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\RenderedBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\StructuredDataInterface;
use Hryvinskyi\RichSnippets\Api\Data\StructuredDataInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\HtmlStructuredDataScannerInterface;
use Magento\Framework\DomDocument\DomDocumentFactory;

/**
 * Reads a page into a DOM with libxml's HTML parser, its errors kept internal and its network access off, and finds
 * the JSON-LD blocks and the schema.org microdata in it
 *
 * The page is read as UTF-8. The media type of a script element is compared without its parameters and its case. A
 * block's text is decoded as JSON with a depth limit, big integers kept as text; text that is not JSON of an object
 * or a list has no value. A microdata item counts when it has a schema.org type and is no other item's property.
 */
class StructuredDataScanner implements HtmlStructuredDataScannerInterface
{
    private const UTF8_DECLARATION = '<?xml encoding="UTF-8">';
    private const PARSE_OPTIONS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT;

    /**
     * @param DomDocumentFactory $documents
     * @param SchemaOrgMicrodata $microdata
     * @param JsonLdBlockInterfaceFactory $blocks
     * @param MicrodataItemInterfaceFactory $items
     * @param StructuredDataInterfaceFactory $structuredData
     * @param \DOMXPathFactory $xpaths
     * @param positive-int $maxDepth the deepest JSON a block may nest
     */
    public function __construct(
        private readonly DomDocumentFactory $documents,
        private readonly SchemaOrgMicrodata $microdata,
        private readonly JsonLdBlockInterfaceFactory $blocks,
        private readonly MicrodataItemInterfaceFactory $items,
        private readonly StructuredDataInterfaceFactory $structuredData,
        private readonly \DOMXPathFactory $xpaths,
        private readonly int $maxDepth = 128
    ) {
    }

    /**
     * @inheritDoc
     */
    public function scan(string $html): StructuredDataInterface
    {
        if (trim($html) === '') {
            return $this->structuredData->create(['blocks' => [], 'microdata' => []]);
        }
        $document = $this->documents->create();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML(self::UTF8_DECLARATION . $html, self::PARSE_OPTIONS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = $this->xpaths->create(['document' => $document]);

        return $this->structuredData->create([
            'blocks' => $this->blocksOf($xpath),
            'microdata' => $this->itemsOf($xpath),
        ]);
    }

    /**
     * The JSON-LD blocks of the page
     *
     * @param \DOMXPath $xpath
     * @return list<JsonLdBlockInterface>
     */
    private function blocksOf(\DOMXPath $xpath): array
    {
        $blocks = [];
        foreach ($this->elements($xpath, '//script[@type]') as $script) {
            $type = strtolower(trim(explode(';', $script->getAttribute('type'), 2)[0]));
            if ($type !== RenderedBlockInterface::MEDIA_TYPE) {
                continue;
            }
            $code = trim($script->getAttribute(RenderedBlockInterface::CODE_ATTRIBUTE));
            $json = trim($script->textContent);
            $blocks[] = $this->blocks->create([
                'code' => $code === '' ? null : $code,
                'json' => $json,
                'value' => $this->decoded($json),
            ]);
        }

        return $blocks;
    }

    /**
     * A block's text decoded; null when it is not JSON of an object or a list
     *
     * @param string $json
     * @return array<mixed>|null
     */
    private function decoded(string $json): ?array
    {
        try {
            $value = json_decode($json, true, $this->maxDepth, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * The schema.org items of the page that are no other item's property
     *
     * @param \DOMXPath $xpath
     * @return list<MicrodataItemInterface>
     */
    private function itemsOf(\DOMXPath $xpath): array
    {
        $items = [];
        $query = sprintf(
            '//*[@%s and @%s and not(@%s)]',
            SchemaOrgMicrodata::SCOPE_ATTRIBUTE,
            SchemaOrgMicrodata::TYPE_ATTRIBUTE,
            SchemaOrgMicrodata::PROPERTY_ATTRIBUTE
        );
        foreach ($this->elements($xpath, $query) as $element) {
            $type = $this->microdata->typeName($element->getAttribute(SchemaOrgMicrodata::TYPE_ATTRIBUTE));
            if ($type === '') {
                continue;
            }
            $items[] = $this->items->create(['type' => $type, 'properties' => $this->propertiesOf($xpath, $element)]);
        }

        return $items;
    }

    /**
     * The names of an item's own properties, each once, in page order
     *
     * @param \DOMXPath $xpath
     * @param \DOMElement $item
     * @return list<string>
     */
    private function propertiesOf(\DOMXPath $xpath, \DOMElement $item): array
    {
        $names = [];
        $query = './/*[@' . SchemaOrgMicrodata::PROPERTY_ATTRIBUTE . ']';
        foreach ($this->elements($xpath, $query, $item) as $property) {
            if ($this->scopeOf($property) !== $item) {
                continue;
            }
            $listed = $property->getAttribute(SchemaOrgMicrodata::PROPERTY_ATTRIBUTE);
            foreach ($this->microdata->propertyNames($listed) as $name) {
                $names[$name] = true;
            }
        }

        return array_map('strval', array_keys($names));
    }

    /**
     * The item a property belongs to: the nearest enclosing element with `itemscope`
     *
     * @param \DOMElement $property
     * @return \DOMElement|null
     */
    private function scopeOf(\DOMElement $property): ?\DOMElement
    {
        for ($node = $property->parentNode; $node instanceof \DOMElement; $node = $node->parentNode) {
            if ($node->hasAttribute(SchemaOrgMicrodata::SCOPE_ATTRIBUTE)) {
                return $node;
            }
        }

        return null;
    }

    /**
     * The elements an XPath query finds
     *
     * @param \DOMXPath $xpath
     * @param string $query
     * @param \DOMNode|null $context
     * @return list<\DOMElement>
     */
    private function elements(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): array
    {
        $found = $xpath->query($query, $context);
        $elements = [];
        if ($found === false) {
            return $elements;
        }
        foreach ($found as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
