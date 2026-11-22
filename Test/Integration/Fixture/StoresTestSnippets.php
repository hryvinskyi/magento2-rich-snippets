<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Fixture;

use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Selection\SelectionIndexInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Stores snippets for a test through the module's own factory and repository, so every one passes the snippet rules
 *
 * A test's transaction is rolled back rather than committed, so the selection index never hears of the write the way
 * a commit tells it; what this process remembers of the selection is dropped after each save instead, as the next
 * request would start without it.
 */
trait StoresTestSnippets
{
    /**
     * Store a snippet
     *
     * @param string $code
     * @param string $name
     * @param array<string, mixed> $template
     * @param array{kinds?: list<string>, conditions?: list<array{0: string, 1: string, 2: string}>,
     *     match?: string, tree?: array<string, mixed>, stores?: list<int>, active?: bool, block?: bool,
     *     sortOrder?: int} $placement the conditions as one group of `[field, operator, value]` that `match`
     *     (`all`, the default, or `any`) joins, or as a whole decoded builder `tree`
     * @return SnippetInterface the snippet as stored
     */
    private function storeSnippet(string $code, string $name, array $template, array $placement = []): SnippetInterface
    {
        $objectManager = Bootstrap::getObjectManager();
        $factory = $objectManager->get(SnippetFactoryInterface::class);
        self::assertInstanceOf(SnippetFactoryInterface::class, $factory);
        $types = $objectManager->get(TemplateTypeInterface::class);
        self::assertInstanceOf(TemplateTypeInterface::class, $types);
        $parser = $objectManager->get(ConditionTreeParserInterface::class);
        self::assertInstanceOf(ConditionTreeParserInterface::class, $parser);
        $vocabulary = $objectManager->get(PlacementVocabularyInterface::class);
        self::assertInstanceOf(PlacementVocabularyInterface::class, $vocabulary);
        $repository = $objectManager->get(SnippetRootRepositoryInterface::class);
        self::assertInstanceOf(SnippetRootRepositoryInterface::class, $repository);

        $snippet = $factory->draft($code, $name, $placement['block'] ?? false);
        $snippet->changeTemplate((string) json_encode($template, JSON_THROW_ON_ERROR), $types);
        if (!($placement['block'] ?? false)) {
            $leaves = [];
            foreach ($placement['conditions'] ?? [] as [$field, $operator, $value]) {
                $leaves[] = [
                    'type' => $field,
                    'attribute' => null,
                    'operator' => $operator,
                    'value' => $value,
                    'is_value_processed' => null,
                ];
            }
            $tree = $placement['tree'] ?? ($leaves === [] ? [] : [
                'type' => 'rich_snippets/combine',
                'attribute' => null,
                'operator' => null,
                'value' => '1',
                'is_value_processed' => null,
                'aggregator' => $placement['match'] ?? 'all',
                'conditions' => $leaves,
            ]);
            $snippet->place(
                $placement['kinds'] ?? [],
                $parser->parseDecoded($tree),
                $placement['stores'] ?? [SnippetInterface::ALL_STORES],
                $vocabulary
            );
        }
        if ($placement['active'] ?? false) {
            $snippet->switchOn();
        }
        $snippet->moveTo($placement['sortOrder'] ?? 0);
        $saved = $repository->save($snippet);
        $index = $objectManager->get(SelectionIndexInterface::class);
        self::assertInstanceOf(ResetAfterRequestInterface::class, $index);
        $index->_resetState();

        return $saved;
    }
}
