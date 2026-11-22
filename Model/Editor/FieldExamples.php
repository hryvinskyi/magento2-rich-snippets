<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Editor;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Engine\TemplatePrefetch;

/**
 * What each field holds on a sample page, read as a snippet reads it
 *
 * The fields are read by one template that names each of them, evaluated the way a snippet is: one batch of the
 * page's data, then one evaluation inside the store view's storefront. So an example value is exactly what a
 * property pointing at the field would get, empty values left out. A field whose path the template language cannot
 * write has no example; a field of a list item has none either, since it only has a value inside its list.
 */
class FieldExamples
{
    private const PATH_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*\z/';
    private const KEYWORDS = ['and', 'or', 'not', 'true', 'false', 'null'];
    private const KEY_PREFIX = 'f';

    /**
     * @param TemplateEngineInterface $engine the snippets' own engine
     * @param TemplatePrefetch $prefetch
     * @param PageScopeFactoryInterface $scopes
     * @param FrontendEmulationInterface $emulation
     */
    public function __construct(
        private readonly TemplateEngineInterface $engine,
        private readonly TemplatePrefetch $prefetch,
        private readonly PageScopeFactoryInterface $scopes,
        private readonly FrontendEmulationInterface $emulation
    ) {
    }

    /**
     * The value of each field on a page
     *
     * @param PageEntityInterface $page
     * @param list<FieldInterface> $fields
     * @return array<string, mixed> by path; a field with no value on the page is left out
     */
    public function onPage(PageEntityInterface $page, array $fields): array
    {
        $paths = [];
        $template = [];
        foreach ($fields as $field) {
            $path = $field->getPath();
            if (!$this->isWritable($path)) {
                continue;
            }
            $key = self::KEY_PREFIX . count($paths);
            $paths[$key] = $path;
            $template[$key] = '{{' . $path . '}}';
        }
        if ($template === []) {
            return [];
        }
        $source = (string) json_encode($template, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $this->emulation->run($page->getStoreId(), function () use ($page, $source, $paths): array {
            try {
                $this->prefetch->forPage($page, [$this->engine->compile($source)]);
                $values = $this->engine->evaluate($source, $this->scopes->create($page));
            } catch (CompileException) {
                return [];
            }
            $examples = [];
            foreach (is_array($values) ? $values : [] as $key => $value) {
                if (isset($paths[$key])) {
                    $examples[$paths[$key]] = $value;
                }
            }

            return $examples;
        });
    }

    /**
     * Whether a path can stand in an expression as a variable path
     *
     * @param string $path
     * @return bool
     */
    private function isWritable(string $path): bool
    {
        return preg_match(self::PATH_PATTERN, $path) === 1
            && !in_array(explode('.', $path)[0], self::KEYWORDS, true);
    }
}
