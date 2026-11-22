<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Fixture;

use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Opens the snippet editor as an admin does and reads its island's document, for a backend controller test that
 * also uses AsksAsTheSnippetsPage
 *
 * The page must print the editor's island root with its failure text, link the editor's built entry, the pages'
 * stylesheet and the expression builder's stylesheet, and carry the document as JSON.
 */
trait ReadsTheEditorPage
{
    /**
     * @var string The name the page host prints the editor's island under
     */
    private string $editorIsland = 'rich-snippets-editor';

    /**
     * The page's part of the editor's document
     *
     * @param string $path
     * @return array<mixed>
     */
    private function openEditor(string $path): array
    {
        $this->newExchange();
        $this->httpRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->follow($path);

        $html = (string) $this->httpResponse()->getBody();
        self::assertSame(200, $this->httpResponse()->getHttpResponseCode());
        $xpath = $this->editorHtml($html);
        $root = $this->editorElement($xpath, '//div[@data-role="' . $this->editorIsland . '"]');
        self::assertStringContainsString('rich-snippets-page', $root->getAttribute('class'));
        self::assertNotSame('', $root->getAttribute('data-failed-text'));
        self::assertStringContainsString('Hryvinskyi_RichSnippets/js/admin-pages/editor', $html);
        self::assertStringContainsString('Hryvinskyi_RichSnippets/css/admin-pages', $html);
        self::assertStringContainsString('Hryvinskyi_UiFormElements/css/expression-builder', $html);
        $document = json_decode(
            $this->editorElement($xpath, '//script[@data-role="' . $this->editorIsland . '-config"]')->textContent,
            true
        );
        $page = $this->at($document, 'page');
        self::assertIsArray($page);

        return $page;
    }

    /**
     * Which tab of the module navigation is current, by code
     *
     * @param array<mixed> $page
     * @return array<string, bool>
     */
    private function currentTabs(array $page): array
    {
        $tabs = [];
        foreach ($this->listAt($page, 'shell', 'nav') as $index => $tab) {
            $tabs[$this->textAt($page, 'shell', 'nav', $index, 'code')] = ($tab['current'] ?? false) === true;
        }

        return $tabs;
    }

    /**
     * The phrase keys the editor's island asks for, as its contract lists them
     *
     * @return list<string>
     */
    private function contractKeys(): array
    {
        $contract = json_decode(
            (string) file_get_contents(__DIR__ . '/../../_contract/admin-pages/editor-phrases.json'),
            true
        );
        self::assertIsArray($contract);
        $keys = [];
        foreach ($this->listOf($contract['keys'] ?? null) as $key) {
            self::assertIsString($key);
            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * The list at a path of names
     *
     * @param mixed $document
     * @param string|int ...$path
     * @return list<array<mixed>>
     */
    private function listAt(mixed $document, string|int ...$path): array
    {
        $value = $this->at($document, ...$path);
        self::assertIsArray($value);
        $items = [];
        foreach ($value as $item) {
            self::assertIsArray($item);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * The items of a list, whatever they are
     *
     * @param mixed $value
     * @return list<mixed>
     */
    private function listOf(mixed $value): array
    {
        self::assertIsArray($value);

        return array_values($value);
    }

    /**
     * The text at a path of names
     *
     * @param mixed $document
     * @param string|int ...$path
     * @return string
     */
    private function textAt(mixed $document, string|int ...$path): string
    {
        $value = $this->at($document, ...$path);
        self::assertIsString($value, implode('.', $path));

        return $value;
    }

    /**
     * @param string $html
     * @return \DOMXPath
     */
    private function editorHtml(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);

        return new \DOMXPath($document);
    }

    /**
     * @param \DOMXPath $xpath
     * @param string $query
     * @return \DOMElement
     */
    private function editorElement(\DOMXPath $xpath, string $query): \DOMElement
    {
        $nodes = $xpath->query($query);
        self::assertInstanceOf(\DOMNodeList::class, $nodes);
        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node, $query);

        return $node;
    }
}
