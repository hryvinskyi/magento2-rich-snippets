<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\JsonTemplate\Api\JsonEncoderInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\LibraryTemplatePoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Library\AnswerResolver;
use Hryvinskyi\RichSnippets\Model\Library\AnswerSubstitution;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Gives each check action that puts a property back the value the property has in a library template, so the editor
 * can write it into the draft as it was meant to be
 *
 * A property the draft's template already has is not put back: it is the page's data that leaves it empty, so its
 * action is dropped rather than letting it overwrite what the admin wrote.
 *
 * The templates looked in, in order: the library template the stored snippet was added from, when it is of the
 * draft's type; then every library template of the draft's type. The first that has the property at the action's
 * path gives it: its value as a template part, with the template's questions answered as adding it would answer them
 * in the sample page's store view and its building blocks pointed at the shop's own copies, and the names of the
 * properties it stands before in that template. An action whose property no template has keeps no value; the
 * editor then puts the property back empty, which leaves it out of the output until it is given one.
 *
 * @phpstan-import-type Row from PreviewChecks
 */
class CheckActionValues
{
    private const SEPARATOR = '.';
    private const INCLUDE_KEY = '"@include"';
    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /** The keys of a template object that are not its properties */
    private const NOT_PROPERTIES = ['@context', '@type', '@if', '@each', '@as', '@limit', '@value', '@include'];

    /**
     * @param LibraryTemplatePoolInterface $templates
     * @param SnippetRepositoryInterface $snippets
     * @param AnswerResolver $answers
     * @param AnswerSubstitution $substitution
     * @param JsonEncoderInterface $json
     */
    public function __construct(
        private readonly LibraryTemplatePoolInterface $templates,
        private readonly SnippetRepositoryInterface $snippets,
        private readonly AnswerResolver $answers,
        private readonly AnswerSubstitution $substitution,
        private readonly JsonEncoderInterface $json
    ) {
    }

    /**
     * The rows, each action that puts a property back given its value where a library template has one
     *
     * @param list<Row> $rows
     * @param string $template the draft's template
     * @param string $type the draft's schema.org type
     * @param int|null $snippetId the stored snippet the draft is of; null for a new one
     * @param int $storeId the store view the template's questions are answered for
     * @return list<Row>
     * @throws MalformedLibraryTemplateException|LibraryException|\JsonException
     */
    public function fill(array $rows, string $template, string $type, ?int $snippetId, int $storeId): array
    {
        $draft = json_decode($template, true);
        $draft = is_array($draft) ? $draft : [];
        $sources = null;
        $filled = [];
        foreach ($rows as $row) {
            $action = $row['action'];
            $path = $action['path'] ?? '';
            if ($action === null || $path === '') {
                $filled[] = $row;
                continue;
            }
            $segments = explode(self::SEPARATOR, $path);
            if ($this->located($draft, $segments) !== null) {
                $filled[] = ['action' => null] + $row;
                continue;
            }
            $sources ??= $this->sources($type, $snippetId);
            $found = $this->found($sources, $segments, $storeId);
            $filled[] = $found === null
                ? $row
                : ['action' => ['value' => $found['value'], 'before' => $found['before']] + $action] + $row;
        }

        return $filled;
    }

    /**
     * The library templates the draft's properties may come from, the stored snippet's own first
     *
     * @param string $type
     * @param int|null $snippetId
     * @return list<LibraryTemplateInterface>
     * @throws MalformedLibraryTemplateException
     */
    private function sources(string $type, ?int $snippetId): array
    {
        $origin = $this->originCode($snippetId);
        $first = [];
        $rest = [];
        foreach ($this->templates->all() as $template) {
            if ($template->getType() !== $type) {
                continue;
            }
            if ($template->getCode() === $origin) {
                $first[] = $template;
                continue;
            }
            $rest[] = $template;
        }

        return [...$first, ...$rest];
    }

    /**
     * The library code the stored snippet was added from; empty for none
     *
     * @param int|null $snippetId
     * @return string
     */
    private function originCode(?int $snippetId): string
    {
        if ($snippetId === null) {
            return '';
        }
        try {
            return $this->snippets->get($snippetId)->getOrigin()?->getCode() ?? '';
        } catch (NoSuchEntityException) {
            return '';
        }
    }

    /**
     * The first template's value of the property at the path, with the properties it stands before
     *
     * @param list<LibraryTemplateInterface> $sources
     * @param list<string> $segments
     * @param int $storeId
     * @return array{value: string, before: list<string>}|null
     * @throws LibraryException|\JsonException
     */
    private function found(array $sources, array $segments, int $storeId): ?array
    {
        foreach ($sources as $template) {
            $found = $this->located($template->getTemplate(), $segments);
            if ($found === null) {
                continue;
            }
            if ($this->needsAnswers($found['node'])) {
                $found = $this->located($this->answered($template, $storeId), $segments);
            }
            if ($found !== null) {
                return [
                    'value' => $this->json->encode($found['node'], self::ENCODE_FLAGS),
                    'before' => $found['before'],
                ];
            }
        }

        return null;
    }

    /**
     * The value at the path of a decoded template, and the properties after it in its object
     *
     * Null when the path leads to no property.
     *
     * @param array<mixed> $template
     * @param list<string> $segments
     * @return array{node: mixed, before: list<string>}|null
     */
    private function located(array $template, array $segments): ?array
    {
        $name = array_pop($segments);
        $object = $template;
        foreach ($segments as $segment) {
            $next = $object[$segment] ?? null;
            if (!is_array($next) || array_is_list($next)) {
                return null;
            }
            $object = $next;
        }
        if ($name === null || array_is_list($object) || !array_key_exists($name, $object)) {
            return null;
        }
        $keys = array_map('strval', array_keys($object));
        $after = array_slice($keys, (int) array_search($name, $keys, true) + 1);

        return [
            'node' => $object[$name],
            'before' => array_values(array_diff($after, self::NOT_PROPERTIES)),
        ];
    }

    /**
     * Whether a value holds a question to answer or a building block to point at the shop's copy
     *
     * @param mixed $node
     * @return bool
     * @throws \JsonException
     */
    private function needsAnswers(mixed $node): bool
    {
        return $this->substitution->placeholders([$node]) !== []
            || str_contains($this->json->encode($node, self::ENCODE_FLAGS), self::INCLUDE_KEY);
    }

    /**
     * The template as adding it would write it in a store view
     *
     * Questions are answered and building blocks point at the shop's copies.
     *
     * @param LibraryTemplateInterface $template
     * @param int $storeId
     * @return array<mixed>
     * @throws LibraryException
     */
    private function answered(LibraryTemplateInterface $template, int $storeId): array
    {
        return $this->substitution->substitute(
            $template->getTemplate(),
            $this->answers->chosen($template, [], $storeId),
            $this->shopBlocks()
        );
    }

    /**
     * The code of the shop's building block for each library code it was added from
     *
     * @return array<string, string>
     */
    private function shopBlocks(): array
    {
        $codes = [];
        foreach ($this->snippets->list(null, null, null, true) as $snippet) {
            $origin = $snippet->getOrigin()?->getCode();
            if ($origin !== null && !isset($codes[$origin])) {
                $codes[$origin] = $snippet->getCode();
            }
        }

        return $codes;
    }
}
