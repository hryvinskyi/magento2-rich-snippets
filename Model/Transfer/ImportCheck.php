<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemReason;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\OriginFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Model\Condition\Tree\ConditionTreeFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;

/**
 * Checks one snippet of an import file against the rules a save applies, and resolves its store views and
 * condition values to this shop's
 *
 * The code and the name must pass the snippet rules, the template must compile and name a type, the kinds of page
 * must be known, and the conditions tree must parse as an editor's would: known fields, attributes and operators,
 * groups not nested too deep, each condition about one of the snippet's kinds. A store view this shop lacks is left
 * out and reported; a condition value this shop lacks is reported, and the snippet can then not be written. A
 * building block has no store views to resolve and no conditions.
 *
 * @phpstan-import-type FileSnippet from ImportFileReader
 * @phpstan-type CheckedSnippet array{type: string, includes: list<string>, conditions: ConditionTreeInterface,
 *     storeIds: list<int>, origin: OriginInterface|null, problems: list<ImportProblemInterface>, invalid: bool,
 *     unresolved: bool, storeless: bool}
 */
class ImportCheck
{
    private const RULE = 'rule';

    /**
     * @param SnippetFactoryInterface $snippets
     * @param ConditionTreeParserInterface $parser
     * @param ConditionTreeFactoryInterface $trees
     * @param OriginFactoryInterface $origins
     * @param TemplateEngineInterface $engine
     * @param TemplateTypeInterface $types
     * @param PlacementVocabularyInterface $vocabulary
     * @param PortableConditions $portableConditions
     * @param PortableStores $portableStores
     * @param TransferFactoryInterface $results
     */
    public function __construct(
        private readonly SnippetFactoryInterface $snippets,
        private readonly ConditionTreeParserInterface $parser,
        private readonly ConditionTreeFactoryInterface $trees,
        private readonly OriginFactoryInterface $origins,
        private readonly TemplateEngineInterface $engine,
        private readonly TemplateTypeInterface $types,
        private readonly PlacementVocabularyInterface $vocabulary,
        private readonly PortableConditions $portableConditions,
        private readonly PortableStores $portableStores,
        private readonly TransferFactoryInterface $results
    ) {
    }

    /**
     * Check a snippet of the file
     *
     * @param FileSnippet $entry
     * @return CheckedSnippet
     */
    public function check(array $entry): array
    {
        $problems = [];
        $storeIds = [SnippetInterface::ALL_STORES];
        if (!$entry['block']) {
            $stores = $this->portableStores->resolve($entry['stores']);
            foreach ($stores['unknown'] as $code) {
                $problems[] = $this->results->problem(ImportProblemReason::StoreUnknown, ['store' => $code]);
            }
            $storeIds = $stores['ids'];
        }
        $checked = [
            'type' => $this->types->typesOf($entry['template'])[0] ?? '',
            'includes' => [],
            'conditions' => $this->trees->tree(),
            'storeIds' => $storeIds,
            'origin' => null,
            'problems' => $problems,
            'invalid' => false,
            'unresolved' => false,
            'storeless' => $storeIds === [],
        ];
        try {
            return $this->checkedRules($entry, $checked);
        } catch (SnippetRuleException $e) {
            $checked['problems'][] = $this->results->problem(
                ImportProblemReason::RuleBroken,
                [self::RULE => $e->getReason()->value] + $e->getParams()
            );
        } catch (CompileException $e) {
            $error = $e->getErrors()[0] ?? null;
            $checked['problems'][] = $this->results->problem(
                ImportProblemReason::TemplateInvalid,
                ['path' => (string) $error?->getPath(), 'error' => (string) $error?->getCode()]
            );
        }
        $checked['invalid'] = true;

        return $checked;
    }

    /**
     * The check carried through the snippet rules
     *
     * @param FileSnippet $entry
     * @param CheckedSnippet $checked
     * @return CheckedSnippet
     * @throws SnippetRuleException|CompileException
     */
    private function checkedRules(array $entry, array $checked): array
    {
        $draft = $this->snippets->draft($entry['code'], $entry['name'], $entry['block']);
        $draft->changeTemplate($entry['template'], $this->types);
        $checked['includes'] = $this->engine->compile($entry['template'])->getIncludes();
        $draft->projectType($this->types);
        $checked['type'] = $draft->getType();
        $origin = $entry['origin'];
        $checked['origin'] = $origin === null ? null : $this->origins->create($origin['code'], $origin['version']);
        if (!$entry['block']) {
            $checked = $this->withConditions($entry['conditions'], $checked);
            $draft->place(
                $entry['pageKinds'],
                $checked['conditions'],
                $checked['storeIds'] === [] ? [SnippetInterface::ALL_STORES] : $checked['storeIds'],
                $this->vocabulary
            );
        }

        return $checked;
    }

    /**
     * The check with the snippet's conditions resolved to this shop's values and parsed
     *
     * @param array<mixed> $conditions the file's tree
     * @param CheckedSnippet $checked
     * @return CheckedSnippet
     * @throws SnippetRuleException
     */
    private function withConditions(array $conditions, array $checked): array
    {
        $resolved = $this->portableConditions->resolved($conditions);
        foreach ($resolved['unresolved'] as $missing) {
            $checked['unresolved'] = true;
            $checked['problems'][] = $this->results->problem(ImportProblemReason::ConditionUnresolved, $missing);
        }
        $checked['conditions'] = $this->parser->parseDecoded($resolved['tree']);

        return $checked;
    }
}
