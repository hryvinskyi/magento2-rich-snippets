<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet\Storage;

use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreePrinterInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\OriginFactoryInterface;
use Hryvinskyi\RichSnippets\Model\ResourceModel\Snippet as SnippetResource;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Maps a snippet to its stored row and back
 *
 * The list columns hold JSON: the kinds of page as a list of codes, the store views as a list of IDs. The conditions
 * column holds the expression builder's own text, written back byte for byte as the builder wrote it, and empty for
 * none. A row that does not read as a snippet is refused rather than read as an empty one.
 */
class SnippetRowMapper
{
    /**
     * @param SnippetFactoryInterface $snippets
     * @param ConditionTreeParserInterface $parser
     * @param ConditionTreePrinterInterface $printer
     * @param OriginFactoryInterface $origins
     * @param SerializerInterface $json
     */
    public function __construct(
        private readonly SnippetFactoryInterface $snippets,
        private readonly ConditionTreeParserInterface $parser,
        private readonly ConditionTreePrinterInterface $printer,
        private readonly OriginFactoryInterface $origins,
        private readonly SerializerInterface $json
    ) {
    }

    /**
     * The snippet a stored row holds
     *
     * @param array<string,mixed> $row
     * @return SnippetRootInterface
     * @throws \UnexpectedValueException when the row does not read as a snippet
     */
    public function toSnippet(array $row): SnippetRootInterface
    {
        return $this->snippets->restore(
            $this->number($row, SnippetResource::ID),
            $this->text($row, SnippetResource::CODE),
            $this->text($row, SnippetResource::NAME),
            $this->text($row, SnippetResource::TEMPLATE),
            $this->text($row, SnippetResource::TYPE),
            (bool) $this->number($row, SnippetResource::IS_BLOCK),
            (bool) $this->number($row, SnippetResource::ACTIVE),
            $this->kinds($row),
            $this->conditions($row),
            $this->storeIds($row),
            $this->origin($row),
            $this->number($row, SnippetResource::SORT_ORDER) ?? 0,
            $this->nullableText($row, SnippetResource::UPDATED_AT),
            $this->text($row, SnippetResource::UPDATED_BY)
        );
    }

    /**
     * The row that stores a snippet, without its ID and timestamps
     *
     * @param SnippetInterface $snippet
     * @param string $author the user name to record as the last to save it
     * @return array<string, scalar|null>
     */
    public function toRow(SnippetInterface $snippet, string $author): array
    {
        $origin = $snippet->getOrigin();

        return [
            SnippetResource::CODE => $snippet->getCode(),
            SnippetResource::NAME => $snippet->getName(),
            SnippetResource::TYPE => $snippet->getType(),
            SnippetResource::IS_BLOCK => (int) $snippet->isBlock(),
            SnippetResource::ACTIVE => (int) $snippet->isActive(),
            SnippetResource::PAGE_KINDS => $this->encode($snippet->getPageKinds()),
            SnippetResource::CONDITIONS => $this->printer->print($snippet->getConditions()),
            SnippetResource::STORE_IDS => $this->encode($snippet->getStoreIds()),
            SnippetResource::TEMPLATE => $snippet->getTemplate(),
            SnippetResource::ORIGIN_CODE => $origin?->getCode(),
            SnippetResource::ORIGIN_VERSION => $origin?->getVersion(),
            SnippetResource::SORT_ORDER => $snippet->getSortOrder(),
            SnippetResource::UPDATED_BY => $author,
        ];
    }

    /**
     * The kinds of page of a row
     *
     * @param array<string,mixed> $row
     * @return list<string>
     */
    private function kinds(array $row): array
    {
        $kinds = [];
        foreach ($this->list($row, SnippetResource::PAGE_KINDS) as $kind) {
            if (!is_string($kind)) {
                throw new \UnexpectedValueException('The stored snippet has a kind of page that is not a code.');
            }
            $kinds[] = $kind;
        }

        return $kinds;
    }

    /**
     * The conditions of a row
     *
     * @param array<string,mixed> $row
     * @return ConditionTreeInterface
     */
    private function conditions(array $row): ConditionTreeInterface
    {
        try {
            return $this->parser->restore($this->text($row, SnippetResource::CONDITIONS));
        } catch (SnippetRuleException $e) {
            throw new \UnexpectedValueException('The stored snippet has conditions that do not read.', 0, $e);
        }
    }

    /**
     * The store views of a row
     *
     * @param array<string,mixed> $row
     * @return list<int>
     */
    private function storeIds(array $row): array
    {
        $storeIds = [];
        foreach ($this->list($row, SnippetResource::STORE_IDS) as $storeId) {
            if (!is_int($storeId)) {
                throw new \UnexpectedValueException('The stored snippet has a store view that is not an ID.');
            }
            $storeIds[] = $storeId;
        }

        return $storeIds;
    }

    /**
     * The origin of a row; null when it has none
     *
     * @param array<string,mixed> $row
     * @return OriginInterface|null
     */
    private function origin(array $row): ?OriginInterface
    {
        $code = $this->nullableText($row, SnippetResource::ORIGIN_CODE);
        $version = $this->number($row, SnippetResource::ORIGIN_VERSION);

        return $code === null || $version === null ? null : $this->origins->create($code, $version);
    }

    /**
     * The list a JSON column of a row holds
     *
     * @param array<string,mixed> $row
     * @param string $column
     * @return list<mixed>
     */
    private function list(array $row, string $column): array
    {
        $decoded = $this->json->unserialize($this->text($row, $column));
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new \UnexpectedValueException('The stored snippet\'s ' . $column . ' is not a JSON list.');
        }

        return $decoded;
    }

    /**
     * A text column of a row; empty when it is null
     *
     * @param array<string,mixed> $row
     * @param string $column
     * @return string
     */
    private function text(array $row, string $column): string
    {
        return $this->nullableText($row, $column) ?? '';
    }

    /**
     * A text column of a row
     *
     * @param array<string,mixed> $row
     * @param string $column
     * @return string|null
     */
    private function nullableText(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * A whole-number column of a row
     *
     * @param array<string,mixed> $row
     * @param string $column
     * @return int|null
     */
    private function number(array $row, string $column): ?int
    {
        $value = $row[$column] ?? null;

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    /**
     * A list as JSON text
     *
     * @param array<mixed> $value
     * @return string
     */
    private function encode(array $value): string
    {
        $json = $this->json->serialize($value);

        return is_string($json) ? $json : '[]';
    }
}
