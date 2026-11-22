<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Data\QuestionSuggestionsInterface;
use Hryvinskyi\RichSnippets\Api\QuestionSuggesterInterface;
use Hryvinskyi\RichSnippets\Model\Config\KeptDefinitions;
use Hryvinskyi\RichSnippets\Model\Library\AnswerSubstitution;
use Hryvinskyi\RichSnippets\Model\Library\LibraryDefinitionParser;
use Hryvinskyi\RichSnippets\Model\Library\LibraryQuestion;
use Hryvinskyi\RichSnippets\Model\Library\LibraryTemplate;
use Hryvinskyi\RichSnippets\Model\Library\LibraryTemplateFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Library\LibraryTemplatePool;
use Hryvinskyi\RichSnippets\Model\Library\Question\QuestionSuggesterPool;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Builds the library's parser and pool over fakes: the shop's kinds of page and condition fields, the module's
 * requirement rules, a suggester pool knowing the module's four suggester codes, and an engine that compiles only
 * what a given check accepts
 */
class LibraryFixture
{
    public const GROUPS = [
        'shop' => 'The shop',
        'product' => 'Product pages',
        'category' => 'Category pages',
        'content' => 'Content pages',
        'blocks' => 'Building blocks',
    ];

    /**
     * The parser over the fakes
     *
     * @return LibraryDefinitionParser
     */
    public static function parser(): LibraryDefinitionParser
    {
        $suggester = new class implements QuestionSuggesterInterface {
            public function suggest(LibraryQuestionInterface $question, int $storeId): QuestionSuggestionsInterface
            {
                throw new \LogicException('Not asked in these tests.');
            }
        };

        return new LibraryDefinitionParser(
            self::templateFactory(),
            PlacementFixture::parser(),
            PlacementFixture::vocabulary(),
            new QuestionSuggesterPool([
                'brand' => $suggester,
                'gtin' => $suggester,
                'description' => $suggester,
                'social_profiles' => $suggester,
            ]),
            ModuleRules::pool(),
            new AnswerSubstitution()
        );
    }

    /**
     * A pool of sources, its compile step refusing the sources a check names
     *
     * @param array<string,\Hryvinskyi\RichSnippets\Api\LibraryTemplateSourceInterface> $sources
     * @param InMemoryCache $cache
     * @param callable(string):bool|null $compiles whether a template source compiles; all do when null
     * @return LibraryTemplatePool
     */
    public static function pool(array $sources, InMemoryCache $cache, ?callable $compiles = null): LibraryTemplatePool
    {
        return new LibraryTemplatePool(
            self::parser(),
            new AnswerSubstitution(),
            new CompilingEngineFake(\Closure::fromCallable($compiles ?? static fn (string $source): bool => true)),
            new KeptDefinitions($cache, new Json()),
            self::GROUPS,
            $sources
        );
    }

    /**
     * Template factory creating the module's own template and question classes
     *
     * @return LibraryTemplateFactoryInterface
     */
    public static function templateFactory(): LibraryTemplateFactoryInterface
    {
        return new class implements LibraryTemplateFactoryInterface {
            public function template(
                string $code,
                int $version,
                string $group,
                string $name,
                string $type,
                string $icon,
                string $description,
                string $where,
                bool $block,
                GoogleStatus $googleStatus,
                string $googleShort,
                string $googleLong,
                array $resultTypes,
                array $properties,
                array $uses,
                array $questions,
                array $pageKinds,
                ConditionTreeInterface $conditions,
                array $template,
                string $summary = ''
            ): LibraryTemplateInterface {
                return new LibraryTemplate(
                    $code,
                    $version,
                    $group,
                    $name,
                    $type,
                    $icon,
                    $description,
                    $where,
                    $block,
                    $googleStatus,
                    $googleShort,
                    $googleLong,
                    $resultTypes,
                    $properties,
                    $uses,
                    $questions,
                    $pageKinds,
                    $conditions,
                    $template,
                    $summary
                );
            }

            public function question(
                string $code,
                string $label,
                string $suggester,
                string $root
            ): LibraryQuestionInterface {
                return new LibraryQuestion($code, $label, $suggester, $root);
            }
        };
    }
}
