<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Editor;

use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\PreviewState;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorPresenter;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\PreviewChecks;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\PreviewPresenter;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\ProblemPhrases;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionLeaf;
use Hryvinskyi\RichSnippets\Model\Draft\DraftPreview;
use Hryvinskyi\RichSnippets\Model\Draft\DraftProblem;
use Hryvinskyi\RichSnippets\Model\Page\PageEntity;
use Hryvinskyi\RichSnippets\Model\Page\PageMatch;
use Hryvinskyi\RichSnippets\Model\Placement\ConditionMismatch;
use Hryvinskyi\RichSnippets\Model\Snippet;
use Hryvinskyi\RichSnippets\Model\Snippet\Origin;
use Hryvinskyi\RichSnippets\Model\Wording\WordList;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\EnglishPlurals;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\ModuleChecker;
use Hryvinskyi\RichSnippets\Test\Unit\Fake\PlacementFixture;
use Hryvinskyi\RichSnippets\Test\Unit\Model\Requirement\DesignPreviews;
use PHPUnit\Framework\TestCase;

/**
 * The editor's answers are written exactly as the contract files the editor's browser code is tested against
 */
class EditorAnswersContractTest extends TestCase
{
    private const CONTRACTS = __DIR__ . '/../../../../_contract/admin-pages/';

    public function testALoadedSnippetIsWrittenAsTheContractWritesIt(): void
    {
        $load = $this->contract('editor-load.json')['data'] ?? null;
        self::assertIsArray($load);

        self::assertSame($load['snippet'] ?? null, self::presented()['load']);
    }

    public function testTheProblemsOfADraftAreWrittenAsTheContractWritesThem(): void
    {
        self::assertSame($this->contract('editor-draft.json')['validate'] ?? null, self::presented()['validate']);
    }

    public function testEachStateOfAPreviewIsWrittenAsTheContractWritesIt(): void
    {
        self::assertSame($this->contract('editor-draft.json')['preview'] ?? null, self::presented()['preview']);
    }

    public function testASavedAndARefusedSaveAreWrittenAsTheContractWritesThem(): void
    {
        $contract = $this->contract('editor-save.json');

        self::assertSame($contract['saved'] ?? null, self::presented()['saved']);
        self::assertSame($contract['refused'] ?? null, self::presented()['refused']);
    }

    /**
     * Every answer the presenters write for the design's Product snippet, its sleeve and its weekender
     *
     * @return array<mixed> as the answers travel: written as JSON and read back
     */
    public static function presented(): array
    {
        $answers = json_decode(
            (string) json_encode(self::answers(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($answers);

        return $answers;
    }

    /**
     * The answers the presenters write
     *
     * @return array<string, mixed>
     */
    private static function answers(): array
    {
        $presenter = new EditorPresenter(new ProblemPhrases(), PlacementFixture::printer());
        $previews = new PreviewPresenter($presenter, new PreviewChecks(new EnglishPlurals(), new WordList()));
        $sleeve = new PageEntity('product', 12, 1);
        $weekender = new PageEntity('product', 14, 1);
        $sleeveMatch = new PageMatch($sleeve, 'Card sleeve', 'https://linden.example/bags/card-sleeve.html');
        $weekenderMatch = new PageMatch($weekender, 'Weekender', 'https://linden.example/bags/weekender.html');
        $trace = DesignPreviews::sleeveTrace();
        $check = ModuleChecker::create()->checkOutput('Product', DesignPreviews::sleeve(), $trace);
        $problems = [
            new DraftProblem('template', '$.name', 'unknown-filter', ['filter' => 'shout'], 14, true),
            new DraftProblem('type', '$', 'type_missing', [], null, true),
            new DraftProblem('pageKinds', '$', DraftProblemInterface::NO_PAGE_KINDS, [], null, false),
        ];
        $notConfigurable = new ConditionLeaf('product_type', null, '!=', 'configurable');

        return [
            'load' => $presenter->snippet(self::product()),
            'validate' => ['problems' => $presenter->problems($problems)],
            'preview' => [
                'output' => $previews->present(
                    new DraftPreview(
                        PreviewState::Output,
                        $sleeve,
                        DesignPreviews::sleeve(),
                        $trace->getEntries(),
                        [],
                        [],
                        $check
                    ),
                    $sleeveMatch,
                    'Product'
                ),
                'conditionsNotMet' => $previews->present(
                    new DraftPreview(
                        PreviewState::ConditionsNotMet,
                        $weekender,
                        null,
                        [],
                        [new ConditionMismatch(
                            $notConfigurable,
                            'product type other than Configurable',
                            'Product type',
                            'is not',
                            ['Configurable'],
                            'Configurable'
                        )],
                        [self::variants()],
                        null,
                        [],
                        'Product type is not Configurable'
                    ),
                    $weekenderMatch,
                    'Product'
                ),
                'noType' => $previews->present(
                    new DraftPreview(PreviewState::NoType, $sleeve, null, [], [], [], null, [$problems[1]]),
                    $sleeveMatch,
                    ''
                ),
            ],
            'saved' => ['snippet' => $presenter->snippet(self::product()), 'problems' => []],
            'refused' => ['snippet' => null, 'problems' => $presenter->problems([$problems[1]])],
        ];
    }

    /**
     * The design's Product snippet, as added from the library
     *
     * @return Snippet
     */
    private static function product(): Snippet
    {
        return new Snippet(
            3,
            'product',
            'Product',
            "{\n    \"@context\": \"https://schema.org\",\n    \"@type\": \"Product\",\n"
                . "    \"name\": \"{{product.name}}\",\n"
                . "    \"offers\": {\"@type\": \"Offer\","
                . " \"hasMerchantReturnPolicy\": {\"@include\": \"return-policy\"}}\n"
                . "}",
            'Product',
            false,
            true,
            ['product'],
            PlacementFixture::leaves([['product_type', '!=', 'configurable']]),
            [0],
            new Origin('product', 3),
            0,
            '2026-11-12 09:30:00',
            'admin'
        );
    }

    /**
     * The design's Product with variants snippet
     *
     * @return Snippet
     */
    private static function variants(): Snippet
    {
        return new Snippet(
            4,
            'product-variants',
            'Product with variants',
            '{"@type": "ProductGroup"}',
            'Product',
            false,
            true,
            ['product'],
            PlacementFixture::leaves([['product_type', '==', 'configurable']]),
            [0],
            null,
            0,
            '2026-11-12 09:30:00',
            'admin'
        );
    }

    /**
     * A contract file, decoded
     *
     * @param string $file
     * @return array<mixed>
     */
    private function contract(string $file): array
    {
        $contract = json_decode((string) file_get_contents(self::CONTRACTS . $file), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($contract);

        return $contract;
    }
}
