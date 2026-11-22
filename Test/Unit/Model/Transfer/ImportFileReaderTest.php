<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Exception\ImportReason;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetImportException;
use Hryvinskyi\RichSnippets\Model\Transfer\ImportFileReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An import file is read into its header and snippets, with defaults for what a snippet leaves out, and anything
 * that is not an export this module reads is refused whole with the reason the Snippets page words
 */
class ImportFileReaderTest extends TestCase
{
    private const TREE = [
        'type' => 'rich_snippets/combine',
        'attribute' => null,
        'operator' => null,
        'value' => '1',
        'is_value_processed' => null,
        'aggregator' => 'any',
        'conditions' => [[
            'type' => 'attribute_set',
            'attribute' => null,
            'operator' => '!=',
            'value' => '4',
            'is_value_processed' => null,
            'labels' => ['Default'],
        ]],
    ];

    public function testAnExportIsReadWithItsHeaderAndEverySnippetMember(): void
    {
        $read = (new ImportFileReader())->read((string) json_encode([
            'hryvinskyi_rich_snippets' => [
                'version' => 1,
                'exportedAt' => '2026-11-13T10:00:00+00:00',
                'source' => 'staging.example.com',
            ],
            'snippets' => [
                [
                    'code' => 'product',
                    'name' => 'Product',
                    'block' => false,
                    'active' => true,
                    'pageKinds' => ['product'],
                    'conditions' => self::TREE,
                    'stores' => ['default', 'trade'],
                    'sortOrder' => 3,
                    'origin' => ['code' => 'product', 'version' => 2],
                    'updatedAt' => '2026-11-12 10:00:00',
                    'template' => '{"@type": "Product"}',
                ],
            ],
        ]));

        self::assertSame('2026-11-13T10:00:00+00:00', $read['exportedAt']);
        self::assertSame('staging.example.com', $read['source']);
        self::assertSame([[
            'code' => 'product',
            'name' => 'Product',
            'block' => false,
            'pageKinds' => ['product'],
            'conditions' => self::TREE,
            'stores' => ['default', 'trade'],
            'sortOrder' => 3,
            'origin' => ['code' => 'product', 'version' => 2],
            'updatedAt' => '2026-11-12 10:00:00',
            'template' => '{"@type": "Product"}',
        ]], $read['snippets']);
    }

    public function testASnippetThatLeavesMembersOutTakesTheDefaults(): void
    {
        $read = (new ImportFileReader())->read($this->export([['code' => 'faq', 'name' => 'FAQ', 'template' => '{}']]));

        self::assertSame('', $read['exportedAt']);
        self::assertSame([[
            'code' => 'faq',
            'name' => 'FAQ',
            'block' => false,
            'pageKinds' => [],
            'conditions' => [],
            'stores' => ['*'],
            'sortOrder' => 0,
            'origin' => null,
            'updatedAt' => null,
            'template' => '{}',
        ]], $read['snippets']);
    }

    /**
     * @return array<string, array{0: string, 1: ImportReason}>
     */
    public static function refusedFiles(): array
    {
        $snippet = ['code' => 'a', 'name' => 'A', 'template' => '{}'];
        $header = ['version' => 1];

        return [
            'text that is not JSON' => ['{"snippets": [', ImportReason::NotJson],
            'JSON without the header' => [(string) json_encode(['snippets' => [$snippet]]), ImportReason::NotAnExport],
            'JSON without the snippets list' => [
                (string) json_encode(['hryvinskyi_rich_snippets' => $header, 'products' => []]),
                ImportReason::NotAnExport,
            ],
            'a list instead of a document' => ['[1, 2]', ImportReason::NotAnExport],
            'another format version' => [
                (string) json_encode(['hryvinskyi_rich_snippets' => ['version' => 2], 'snippets' => []]),
                ImportReason::VersionUnsupported,
            ],
            'a snippet without a template' => [
                (string) json_encode([
                    'hryvinskyi_rich_snippets' => $header,
                    'snippets' => [['code' => 'a', 'name' => 'A']],
                ]),
                ImportReason::SnippetMalformed,
            ],
            'a list of conditions instead of a tree' => [
                (string) json_encode(['hryvinskyi_rich_snippets' => $header, 'snippets' => [$snippet + [
                    'conditions' => [['field' => 'product_type', 'op' => 'is', 'value' => 'simple']],
                ]]]),
                ImportReason::SnippetMalformed,
            ],
            'conditions that are text' => [
                (string) json_encode([
                    'hryvinskyi_rich_snippets' => $header,
                    'snippets' => [$snippet + ['conditions' => 'product_type is simple']],
                ]),
                ImportReason::SnippetMalformed,
            ],
            'two snippets with one code' => [
                (string) json_encode(['hryvinskyi_rich_snippets' => $header, 'snippets' => [$snippet, $snippet]]),
                ImportReason::CodeRepeated,
            ],
        ];
    }

    #[DataProvider('refusedFiles')]
    public function testAFileThatIsNotAReadableExportIsRefusedWithItsReason(string $text, ImportReason $reason): void
    {
        try {
            (new ImportFileReader())->read($text);
            self::fail('The file was read.');
        } catch (SnippetImportException $e) {
            self::assertSame($reason, $e->getReason());
        }
    }

    public function testAFileLargerThanTwoMegabytesIsRefusedBeforeItIsDecoded(): void
    {
        $text = $this->export([
            ['code' => 'a', 'name' => str_repeat('x', ImportFileReader::MAX_BYTES), 'template' => '{}'],
        ]);

        $this->expectExceptionObject(new SnippetImportException(ImportReason::FileTooLarge));

        (new ImportFileReader())->read($text);
    }

    public function testAFileNestedDeeperThanSixtyFourLevelsIsRefusedAsTooDeep(): void
    {
        $text = '{"hryvinskyi_rich_snippets": {"version": 1}, "snippets": [], "deep": '
            . str_repeat('[', 70) . str_repeat(']', 70) . '}';

        try {
            (new ImportFileReader())->read($text);
            self::fail('The file was read.');
        } catch (SnippetImportException $e) {
            self::assertSame(ImportReason::TooDeep, $e->getReason());
        }
    }

    public function testAMalformedSnippetIsNamedByItsPlaceAndMember(): void
    {
        try {
            (new ImportFileReader())->read($this->export([
                ['code' => 'a', 'name' => 'A', 'template' => '{}'],
                ['code' => 'b', 'name' => 'B', 'template' => '{}', 'sortOrder' => 'first'],
            ]));
            self::fail('The file was read.');
        } catch (SnippetImportException $e) {
            self::assertSame(ImportReason::SnippetMalformed, $e->getReason());
            self::assertSame(['index' => '1', 'field' => 'sortOrder'], $e->getParams());
        }
    }

    /**
     * An export holding snippets
     *
     * @param list<array<string, mixed>> $snippets
     * @return string
     */
    private function export(array $snippets): string
    {
        return (string) json_encode(['hryvinskyi_rich_snippets' => ['version' => 1], 'snippets' => $snippets]);
    }
}
