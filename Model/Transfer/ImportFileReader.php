<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Exception\ImportReason;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetImportException;

/**
 * Reads the text of an import file into its header and snippets, refusing anything that is not an export this module
 * can read
 *
 * The text is at most 2 MB and nests at most 64 levels. The document must carry the module's header, in format
 * version 1, and a list of snippets. Each snippet needs a code, a name and its template as text; every other member
 * may be left out (a building block: no; switched off; no kinds of page; no conditions; every store view; first in
 * the order; no origin; no time of change), but one that is there must have the right kind of value. The conditions
 * are the expression builder's tree, an object, or null or empty text for none. Two snippets with the same code are
 * refused. What the values mean is not checked here.
 *
 * @phpstan-type FileSnippet array{code: string, name: string, block: bool, pageKinds: list<string>,
 *     conditions: array<mixed>, stores: list<string>, sortOrder: int,
 *     origin: array{code: string, version: int}|null, updatedAt: string|null, template: string}
 * @phpstan-type ReadFile array{exportedAt: string, source: string, snippets: list<FileSnippet>}
 */
class ImportFileReader
{
    public const MAX_BYTES = 2097152;
    public const MAX_DEPTH = 64;

    /**
     * Read a file's text
     *
     * @param string $text
     * @return ReadFile
     * @throws SnippetImportException
     */
    public function read(string $text): array
    {
        if (strlen($text) > self::MAX_BYTES) {
            throw new SnippetImportException(ImportReason::FileTooLarge);
        }
        $document = $this->decoded($text);
        $header = $document[ExportFormat::HEADER] ?? null;
        $snippets = $document[ExportFormat::SNIPPETS] ?? null;
        if (!is_array($header) || array_is_list($header) || !is_array($snippets) || !array_is_list($snippets)) {
            throw new SnippetImportException(ImportReason::NotAnExport);
        }
        $version = $header[ExportFormat::VERSION] ?? null;
        if ($version !== ExportFormat::CURRENT_VERSION) {
            throw new SnippetImportException(
                ImportReason::VersionUnsupported,
                ['version' => is_scalar($version) ? (string) $version : '']
            );
        }
        $read = [];
        $codes = [];
        foreach ($snippets as $index => $snippet) {
            $entry = $this->snippet($snippet, $index);
            if (isset($codes[$entry['code']])) {
                throw new SnippetImportException(ImportReason::CodeRepeated, ['code' => $entry['code']]);
            }
            $codes[$entry['code']] = true;
            $read[] = $entry;
        }

        return [
            'exportedAt' => $this->text($header[ExportFormat::EXPORTED_AT] ?? null),
            'source' => $this->text($header[ExportFormat::SOURCE] ?? null),
            'snippets' => $read,
        ];
    }

    /**
     * The decoded document: an object
     *
     * @param string $text
     * @return array<mixed>
     * @throws SnippetImportException
     */
    private function decoded(string $text): array
    {
        try {
            $document = json_decode($text, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SnippetImportException(
                $e->getCode() === JSON_ERROR_DEPTH ? ImportReason::TooDeep : ImportReason::NotJson,
                [],
                $e
            );
        }
        if (!is_array($document) || array_is_list($document)) {
            throw new SnippetImportException(ImportReason::NotAnExport);
        }

        return $document;
    }

    /**
     * One snippet of the file
     *
     * @param mixed $snippet
     * @param int $index
     * @return FileSnippet
     * @throws SnippetImportException
     */
    private function snippet(mixed $snippet, int $index): array
    {
        if (!is_array($snippet) || array_is_list($snippet)) {
            $this->malformed($index, '');
        }
        $sortOrder = $snippet[ExportFormat::SORT_ORDER] ?? 0;
        if (!is_int($sortOrder)) {
            $this->malformed($index, ExportFormat::SORT_ORDER);
        }
        $updatedAt = $snippet[ExportFormat::UPDATED_AT] ?? null;
        if ($updatedAt !== null && !is_string($updatedAt)) {
            $this->malformed($index, ExportFormat::UPDATED_AT);
        }

        return [
            'code' => $this->required($snippet, ExportFormat::CODE, $index),
            'name' => $this->required($snippet, ExportFormat::NAME, $index),
            'block' => $this->flag($snippet, ExportFormat::BLOCK, $index),
            'pageKinds' => $this->texts($snippet, ExportFormat::PAGE_KINDS, [], $index),
            'conditions' => $this->conditions($snippet[ExportFormat::CONDITIONS] ?? [], $index),
            'stores' => $this->texts($snippet, ExportFormat::STORES, [ExportFormat::ALL_STORES], $index),
            'sortOrder' => $sortOrder,
            'origin' => $this->origin($snippet[ExportFormat::ORIGIN] ?? null, $index),
            'updatedAt' => $updatedAt,
            'template' => $this->required($snippet, ExportFormat::TEMPLATE, $index),
        ];
    }

    /**
     * The conditions tree of a snippet; empty for none
     *
     * @param mixed $conditions
     * @param int $index
     * @return array<mixed>
     * @throws SnippetImportException
     */
    private function conditions(mixed $conditions, int $index): array
    {
        if ($conditions === null || $conditions === [] || $conditions === '') {
            return [];
        }
        if (!is_array($conditions) || array_is_list($conditions)) {
            $this->malformed($index, ExportFormat::CONDITIONS);
        }

        return $conditions;
    }

    /**
     * The origin of a snippet
     *
     * @param mixed $origin
     * @param int $index
     * @return array{code: string, version: int}|null
     * @throws SnippetImportException
     */
    private function origin(mixed $origin, int $index): ?array
    {
        if ($origin === null) {
            return null;
        }
        $code = is_array($origin) ? ($origin[ExportFormat::ORIGIN_CODE] ?? null) : null;
        $version = is_array($origin) ? ($origin[ExportFormat::ORIGIN_VERSION] ?? null) : null;
        if (!is_string($code) || !is_int($version)) {
            $this->malformed($index, ExportFormat::ORIGIN);
        }

        return ['code' => $code, 'version' => $version];
    }

    /**
     * A member that must be text
     *
     * @param array<mixed> $snippet
     * @param string $member
     * @param int $index
     * @return string
     * @throws SnippetImportException
     */
    private function required(array $snippet, string $member, int $index): string
    {
        $value = $snippet[$member] ?? null;
        if (!is_string($value)) {
            $this->malformed($index, $member);
        }

        return $value;
    }

    /**
     * A member that is true or false, false when it is left out
     *
     * @param array<mixed> $snippet
     * @param string $member
     * @param int $index
     * @return bool
     * @throws SnippetImportException
     */
    private function flag(array $snippet, string $member, int $index): bool
    {
        $value = $snippet[$member] ?? false;
        if (!is_bool($value)) {
            $this->malformed($index, $member);
        }

        return $value;
    }

    /**
     * A member that is a list of texts
     *
     * @param array<mixed> $snippet
     * @param string $member
     * @param list<string> $default when it is left out
     * @param int $index
     * @return list<string>
     * @throws SnippetImportException
     */
    private function texts(array $snippet, string $member, array $default, int $index): array
    {
        $value = $snippet[$member] ?? $default;
        if (!is_array($value) || !array_is_list($value)) {
            $this->malformed($index, $member);
        }
        $texts = [];
        foreach ($value as $text) {
            if (!is_string($text)) {
                $this->malformed($index, $member);
            }
            $texts[] = $text;
        }

        return $texts;
    }

    /**
     * A header member as text; empty when it is not text
     *
     * @param mixed $value
     * @return string
     */
    private function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * The refusal of a snippet with a member of the wrong kind
     *
     * @param int $index
     * @param string $member
     * @return never
     * @throws SnippetImportException
     */
    private function malformed(int $index, string $member): never
    {
        throw new SnippetImportException(
            ImportReason::SnippetMalformed,
            ['index' => (string) $index, 'field' => $member]
        );
    }
}
