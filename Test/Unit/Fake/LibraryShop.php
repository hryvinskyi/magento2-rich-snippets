<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\HintTone;
use Hryvinskyi\RichSnippets\Api\Data\LibraryEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryReason;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;
use Hryvinskyi\RichSnippets\Model\Config\ModuleJsonFile;
use Hryvinskyi\RichSnippets\Model\Library\AddedSnippet;
use Hryvinskyi\RichSnippets\Model\Library\CopyNames;
use Hryvinskyi\RichSnippets\Model\Library\LibraryEntry;
use Hryvinskyi\RichSnippets\Model\Library\LibraryTemplatePool;
use Hryvinskyi\RichSnippets\Model\Library\Question\AnswerOption;
use Hryvinskyi\RichSnippets\Model\Library\Question\QuestionSuggestions;
use Hryvinskyi\RichSnippets\Model\Library\Source\ModuleLibraryFile;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * The sample shop of the Library page's contract files: the module's thirteen templates as their files define them,
 * a shop that has some of them already, the answers its catalog suggests (184 products, as the design's sample shop)
 * and the adds it ends with
 *
 * A shop is a library in memory: `list()` answers each template with what the shop has of it, `suggestions()` the
 * sample answers, `add()` and `addRecommended()` what they are told to answer, or the refusal they are told to throw;
 * a shop told it is broken fails to list its library with the fault it was given.
 */
class LibraryShop implements LibraryInterface
{
    public const RECOMMENDED = ['organization', 'website-name', 'breadcrumbs', 'product', 'product-variants'];

    /** What a shop with nine templates added has: every one at version 1 */
    public const NINE_ADDED = [
        'organization', 'website-name', 'breadcrumbs', 'product', 'product-variants', 'product-list', 'faq',
        'return-policy', 'postal-address',
    ];

    private const MODULE_FILES = [
        'organization', 'website-name', 'breadcrumbs', 'local-business', 'product', 'product-variants',
        'product-list', 'category', 'faq', 'article', 'return-policy', 'shipping-policy', 'postal-address',
    ];
    private const SHOP = 'Linden & Co.';
    private const TOTAL = 184;

    /**
     * @var list<AddedSnippetInterface>
     */
    private array $added = [];

    /**
     * @var \Throwable|null
     */
    private ?\Throwable $refusal = null;

    /**
     * @var \Throwable|null
     */
    private ?\Throwable $fault = null;

    /**
     * @param LibraryTemplatePool $templates
     * @param array<string, list<int>> $have the versions of the copies the shop has, by template code
     */
    public function __construct(private readonly LibraryTemplatePool $templates, private readonly array $have = [])
    {
    }

    /**
     * The module's templates, read from its library files
     *
     * @return LibraryTemplatePool
     */
    public static function moduleTemplates(): LibraryTemplatePool
    {
        $dirs = new class extends Dir {
            /**
             * A directory of the module itself, whatever module is asked
             */
            public function __construct()
            {
            }

            /**
             * @param string $moduleName
             * @param string $type
             * @return string
             */
            public function getDir($moduleName, $type = '')
            {
                return dirname(__DIR__, 3) . '/etc';
            }
        };
        $files = new ModuleJsonFile($dirs, new File(), new Json());
        $sources = [];
        foreach (self::MODULE_FILES as $code) {
            $sources[$code] = new ModuleLibraryFile($files, 'Hryvinskyi_RichSnippets', $code . '.json');
        }

        return LibraryFixture::pool($sources, new InMemoryCache());
    }

    /**
     * A shop whose copies are at version 1 of each template named
     *
     * @param list<string> $codes
     * @return self
     */
    public static function having(array $codes): self
    {
        return new self(self::moduleTemplates(), array_fill_keys($codes, [1]));
    }

    /**
     * What the next add answers
     *
     * @param list<AddedSnippetInterface> $added
     * @return self
     */
    public function answering(array $added): self
    {
        $this->added = $added;
        $this->refusal = null;

        return $this;
    }

    /**
     * What the next add throws
     *
     * @param \Throwable $refusal
     * @return self
     */
    public function refusing(\Throwable $refusal): self
    {
        $this->refusal = $refusal;

        return $this;
    }

    /**
     * What listing the library throws from now on
     *
     * @param \Throwable $fault
     * @return self
     */
    public function broken(\Throwable $fault): self
    {
        $this->fault = $fault;

        return $this;
    }

    /**
     * A snippet an add ended with
     *
     * @param int $id
     * @param string $code
     * @param string $name
     * @param bool $block
     * @param bool $reused
     * @param string|null $template the template it stands for; the one with its code when left out
     * @return AddedSnippetInterface
     */
    public static function snippet(
        int $id,
        string $code,
        string $name,
        bool $block,
        bool $reused,
        ?string $template = null
    ): AddedSnippetInterface {
        return new AddedSnippet($id, $code, $name, $block, $template ?? $code, $reused);
    }

    /**
     * @inheritDoc
     */
    public function list(): array
    {
        if ($this->fault !== null) {
            throw $this->fault;
        }
        $names = [];
        foreach ($this->templates->all() as $template) {
            if (isset($this->have[$template->getCode()])) {
                $names[$template->getName()] = true;
            }
        }

        return array_map(
            fn ($template): LibraryEntryInterface => new LibraryEntry(
                $template,
                $this->have[$template->getCode()] ?? [],
                array_values(array_filter($template->getUses(), fn (string $use): bool => isset($this->have[$use]))),
                (new CopyNames())->next($template->getName(), $names)
            ),
            $this->templates->all()
        );
    }

    /**
     * @inheritDoc
     */
    public function recommended(): array
    {
        return self::RECOMMENDED;
    }

    /**
     * @inheritDoc
     */
    public function suggestions(string $code): array
    {
        $template = $this->templates->get($code)
            ?? throw new LibraryException(LibraryReason::TemplateUnknown, ['template' => $code]);
        $questions = [];
        foreach ($template->getQuestions() as $question) {
            $questions[] = $this->sample($question->getCode(), $question->getLabel());
        }

        return $questions;
    }

    /**
     * @inheritDoc
     */
    public function add(string $code, array $answers, string $idempotencyKey): array
    {
        if ($this->refusal !== null) {
            throw $this->refusal;
        }

        return $this->added;
    }

    /**
     * @inheritDoc
     */
    public function addRecommended(string $idempotencyKey): array
    {
        return $this->add('', [], $idempotencyKey);
    }

    /**
     * The answers the sample shop's catalog suggests for a question
     *
     * @param string $question
     * @param string $label
     * @return QuestionSuggestionsInterface
     */
    private function sample(string $question, string $label): QuestionSuggestionsInterface
    {
        $ok = HintTone::Ok;
        $warn = HintTone::Warn;
        $samples = [
            'brand' => [[
                new AnswerOption(
                    'attribute:manufacturer',
                    'Manufacturer · attribute manufacturer',
                    false,
                    '{{product.manufacturer}}',
                    'Filled for 31 of 184 products. The others get the shop name.',
                    $ok,
                    31
                ),
                new AnswerOption(
                    'attribute:brand',
                    'Brand · attribute brand',
                    false,
                    '{{product.brand}}',
                    'Filled for 0 of 184 products.',
                    $warn,
                    0
                ),
                new AnswerOption(
                    'shop',
                    'Always “' . self::SHOP . '”',
                    false,
                    self::SHOP,
                    'The same on every product.',
                    $ok
                ),
                new AnswerOption('leave_out', 'Leave it out', true, null, 'Google recommends a brand.', $warn),
            ], 'attribute:manufacturer', self::TOTAL],
            'gtin' => [[
                new AnswerOption(
                    'attribute:ean',
                    'EAN · attribute ean',
                    false,
                    '{{product.ean}}',
                    'Found by its code. Filled for 161 of 184 products.',
                    $ok,
                    161
                ),
                new AnswerOption(
                    'attribute:barcode',
                    'Barcode · attribute barcode',
                    false,
                    '{{product.barcode}}',
                    'Filled for 4 of 184 products.',
                    $warn,
                    4
                ),
                new AnswerOption(
                    'leave_out',
                    'Leave it out',
                    true,
                    null,
                    'Google recommends a GTIN where a product has one.',
                    $warn
                ),
            ], 'attribute:ean', self::TOTAL],
            'description' => [[
                new AnswerOption(
                    'attribute:short_description',
                    'Short description',
                    false,
                    '{{product.short_description | plain}}',
                    'Filled for 184 of 184 products. HTML is removed.',
                    $ok,
                    184
                ),
                new AnswerOption(
                    'attribute:description',
                    'Description',
                    false,
                    '{{product.description | plain}}',
                    'Filled for 184 of 184 products. HTML is removed, cut at 5,000 characters.',
                    $ok,
                    184
                ),
                new AnswerOption(
                    'attribute:meta_description',
                    'Meta description',
                    false,
                    '{{product.meta_description}}',
                    'Filled for 97 of 184 products.',
                    $warn,
                    97
                ),
            ], 'attribute:short_description', self::TOTAL],
            'social_profiles' => [[
                new AnswerOption(
                    'type_in',
                    'Type them in after adding',
                    false,
                    [],
                    'The snippet opens with an empty list under sameAs.',
                    $ok
                ),
                new AnswerOption(
                    'cms_block:footer_links',
                    'Take the 4 links from the CMS block “footer_links”',
                    false,
                    ['https://instagram.example/linden'],
                    '4 links found in the CMS block “footer_links”.',
                    $ok
                ),
            ], 'type_in', null],
        ];
        [$options, $default, $total] = $samples[$question] ?? [[
            new AnswerOption('leave_out', 'Leave it out', true, null, '', $ok),
        ], 'leave_out', null];

        return new QuestionSuggestions($question, $label, $options, $default, $total);
    }
}
