<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Snippets;

use Hryvinskyi\RichSnippets\Api\Data\FlagTone;
use Hryvinskyi\RichSnippets\Api\Data\SnippetFlagInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetRowsPresenter;
use Hryvinskyi\RichSnippets\Model\Flag\SnippetFlag;
use Hryvinskyi\RichSnippets\Model\Listing\SnippetListing;
use Hryvinskyi\RichSnippets\Model\Listing\SnippetRow;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use PHPUnit\Framework\TestCase;

/**
 * The snippet list is presented exactly as the data contract (`snippets-data.json`) writes it, which the Snippets
 * page's browser tests read as the server's answer: every row's snippet, words, counts and references, a flag per
 * tone, and the list's counts
 */
class SnippetRowsPresenterTest extends TestCase
{
    private const CONTRACT = __DIR__ . '/../../../../_contract/admin-pages/snippets-data.json';

    public function testTheListIsPresentedAsTheContractWritesIt(): void
    {
        $data = $this->contract();
        $rows = [];
        foreach ($this->list($data['rows'] ?? null) as $row) {
            $rows[] = $this->row($row);
        }
        $counts = $this->map($data['counts'] ?? null);

        $presented = (new SnippetRowsPresenter(PlacementFixture::printer()))->present(new SnippetListing(
            $rows,
            $this->ints($counts['kinds'] ?? null),
            $this->ints($counts['flags'] ?? null),
            $this->int($counts['attention'] ?? null),
            $this->int($counts['on'] ?? null),
            $this->int($counts['off'] ?? null),
            $this->int($counts['blocks'] ?? null)
        ));

        self::assertSame($data, $presented);
    }

    public function testARowShowsTheFirstFlagOfEachToneAndNullForATone(): void
    {
        $snippet = new Snippet(
            1,
            'faq',
            'FAQ',
            '{}',
            'FAQPage',
            false,
            false,
            [],
            PlacementFixture::none(),
            [0],
            null,
            0,
            null,
            ''
        );
        $row = new SnippetRow($snippet, '', [], 0, [], [], [
            new SnippetFlag(FlagTone::Note, 'first note'),
            new SnippetFlag(FlagTone::Bad, 'first bad'),
            new SnippetFlag(FlagTone::Note, 'second note'),
        ]);

        $presenter = new SnippetRowsPresenter(PlacementFixture::printer());
        $presented = $presenter->present(new SnippetListing([$row], [], [], 0, 0, 1, 0));

        self::assertSame(
            ['bad' => 'first bad', 'warn' => null, 'note' => 'first note'],
            $presented['rows'][0]['flags']
        );
    }

    /**
     * A row built from the contract's values
     *
     * @param array<mixed> $row
     * @return SnippetRow
     */
    private function row(array $row): SnippetRow
    {
        $snippet = new Snippet(
            $this->int($row['id'] ?? null),
            $this->text($row['code'] ?? null),
            $this->text($row['name'] ?? null),
            '{}',
            $this->text($row['type'] ?? null),
            ($row['isBlock'] ?? null) === true,
            ($row['active'] ?? null) === true,
            $this->texts($row['pageKinds'] ?? null),
            PlacementFixture::tree($this->text($row['conditions'] ?? null)),
            array_values($this->ints($row['storeIds'] ?? null)),
            null,
            0,
            $this->text($row['updatedAt'] ?? null),
            $this->text($row['updatedBy'] ?? null)
        );
        $flags = [];
        foreach ($this->map($row['flags'] ?? null) as $tone => $text) {
            if (is_string($text)) {
                $flags[] = new SnippetFlag(FlagTone::from((string) $tone), $text);
            }
        }

        return new SnippetRow(
            $snippet,
            $this->text($row['conditionSummary'] ?? null),
            $this->texts($row['storeNames'] ?? null),
            $this->int($row['propertyCount'] ?? null),
            $this->names($row['uses'] ?? null),
            array_map(fn (?string $name): string => (string) $name, $this->names($row['usedIn'] ?? null)),
            $this->withExtraFlag($flags)
        );
    }

    /**
     * The flags with a later one of the first tone, which the row must not show
     *
     * @param list<SnippetFlagInterface> $flags
     * @return list<SnippetFlagInterface>
     */
    private function withExtraFlag(array $flags): array
    {
        if ($flags === []) {
            return [];
        }
        $flags[] = new SnippetFlag($flags[0]->getTone(), 'a later remark');

        return $flags;
    }

    /**
     * References as names by code
     *
     * @param mixed $references
     * @return array<string, string|null>
     */
    private function names(mixed $references): array
    {
        $names = [];
        foreach ($this->list($references) as $reference) {
            $name = $reference['name'] ?? null;
            $names[$this->text($reference['code'] ?? null)] = is_string($name) ? $name : null;
        }

        return $names;
    }

    /**
     * The contract's data
     *
     * @return array<mixed>
     */
    private function contract(): array
    {
        $decoded = json_decode((string) file_get_contents(self::CONTRACT), true, 512, JSON_THROW_ON_ERROR);

        return $this->map(is_array($decoded) ? ($decoded['data'] ?? null) : null);
    }

    /**
     * @param mixed $value
     * @return list<array<mixed>>
     */
    private function list(mixed $value): array
    {
        self::assertIsArray($value);
        $list = [];
        foreach ($value as $item) {
            self::assertIsArray($item);
            $list[] = $item;
        }

        return $list;
    }

    /**
     * @param mixed $value
     * @return array<mixed>
     */
    private function map(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }

    /**
     * @param mixed $value
     * @return array<string, int>
     */
    private function ints(mixed $value): array
    {
        $ints = [];
        foreach ($this->map($value) as $key => $item) {
            self::assertIsInt($item);
            $ints[(string) $key] = $item;
        }

        return $ints;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function texts(mixed $value): array
    {
        return array_values(array_map(fn (mixed $item): string => $this->text($item), $this->map($value)));
    }

    /**
     * @param mixed $value
     * @return int
     */
    private function int(mixed $value): int
    {
        self::assertIsInt($value);

        return $value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function text(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
