<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\LibraryTemplateSourceInterface;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Api\QuestionSuggesterPoolInterface;
use Hryvinskyi\RichSnippets\Api\ResultTypeRulePoolInterface;

/**
 * Turns one library template definition into a template, refusing any definition that is not exactly the shape a
 * template needs
 *
 * The Google status of a template that names kinds of result is read from their requirement rules — shown when any
 * of them is shown, gone when none is shown and one is gone — so the library and the checks never disagree.
 */
class LibraryDefinitionParser
{
    private const KEYS = ['version', 'group', 'name', 'icon', 'description', 'summary', 'where', 'block', 'google',
        'properties', 'uses', 'placement', 'questions', 'template'];
    private const GOOGLE_KEYS = ['results', 'status', 'short', 'long'];
    private const PLACEMENT_KEYS = ['pageKinds', 'conditions'];
    private const QUESTION_KEYS = ['code', 'label', 'suggester', 'root'];
    private const DEFAULT_ROOT = 'product';
    private const TYPE = '@type';
    private const CONTEXT = '@context';
    private const INCLUDE = '@include';

    /**
     * @param LibraryTemplateFactoryInterface $factory
     * @param ConditionTreeParserInterface $parser
     * @param PlacementVocabularyInterface $vocabulary
     * @param QuestionSuggesterPoolInterface $suggesters
     * @param ResultTypeRulePoolInterface $rules
     * @param AnswerSubstitution $substitution
     */
    public function __construct(
        private readonly LibraryTemplateFactoryInterface $factory,
        private readonly ConditionTreeParserInterface $parser,
        private readonly PlacementVocabularyInterface $vocabulary,
        private readonly QuestionSuggesterPoolInterface $suggesters,
        private readonly ResultTypeRulePoolInterface $rules,
        private readonly AnswerSubstitution $substitution
    ) {
    }

    /**
     * The template a definition describes
     *
     * @param string $code the template's code, its key in the pool
     * @param array<mixed> $definition
     * @param list<string> $groups the group codes the pool declares
     * @return LibraryTemplateInterface
     * @throws MalformedLibraryTemplateException when the definition is malformed
     * @see LibraryTemplateSourceInterface for the shape
     */
    public function parse(string $code, array $definition, array $groups): LibraryTemplateInterface
    {
        if (preg_match(SnippetInterface::CODE_PATTERN, $code) !== 1) {
            throw new MalformedLibraryTemplateException(
                sprintf('"%s" is not a library template code: it follows the snippet code grammar.', $code)
            );
        }
        $this->refuseUnknownKeys($code, '', $definition, self::KEYS);
        $version = $definition['version'] ?? null;
        if (!is_int($version) || $version < 1) {
            $this->fail($code, 'version', 'an integer from 1');
        }
        $group = $this->string($code, 'group', $definition['group'] ?? null);
        if (!in_array($group, $groups, true)) {
            $this->fail($code, 'group', 'one of the groups the library declares');
        }
        $block = $definition['block'] ?? false;
        if (!is_bool($block)) {
            $this->fail($code, 'block', 'true or false');
        }
        $google = $definition['google'] ?? null;
        if (!is_array($google) || array_is_list($google)) {
            $this->fail($code, 'google', 'an object');
        }
        $this->refuseUnknownKeys($code, 'google.', $google, self::GOOGLE_KEYS);
        $results = $this->strings($code, 'google.results', $google['results'] ?? []);
        $uses = $this->strings($code, 'uses', $definition['uses'] ?? []);
        $questions = $this->questions($code, $definition['questions'] ?? []);
        $template = $definition['template'] ?? null;
        if (!is_array($template) || $template === [] || array_is_list($template)) {
            $this->fail($code, 'template', 'a JSON object');
        }
        [$pageKinds, $conditions] = $this->placement($code, $block, $definition['placement'] ?? null);
        $this->checkTemplate($code, $template, $block, $uses, $questions);

        return $this->factory->template(
            $code,
            $version,
            $group,
            $this->string($code, 'name', $definition['name'] ?? null),
            $this->rootType($code, $template),
            $this->string($code, 'icon', $definition['icon'] ?? null),
            $this->string($code, 'description', $definition['description'] ?? null),
            $this->string($code, 'where', $definition['where'] ?? null),
            $block,
            $this->googleStatus($code, $results, $google['status'] ?? null),
            $this->string($code, 'google.short', $google['short'] ?? null),
            $this->string($code, 'google.long', $google['long'] ?? null),
            $results,
            $this->strings($code, 'properties', $definition['properties'] ?? []),
            $uses,
            $questions,
            $pageKinds,
            $conditions,
            $template,
            $this->optionalString($code, 'summary', $definition['summary'] ?? '')
        );
    }

    /**
     * The status Google gives the template's kinds of result, or the one the definition states when it names none
     *
     * @param string $code
     * @param list<string> $results
     * @param mixed $status
     * @return GoogleStatus
     * @throws MalformedLibraryTemplateException
     */
    private function googleStatus(string $code, array $results, mixed $status): GoogleStatus
    {
        if ($results === []) {
            $stated = is_string($status) ? GoogleStatus::tryFrom($status) : null;

            return $stated ?? $this->fail($code, 'google.status', 'one of "ok", "none" and "gone"');
        }
        if ($status !== null) {
            $this->fail($code, 'google.status', 'absent: the status comes from the rules in "results"');
        }
        $statuses = [];
        foreach ($results as $index => $result) {
            $rule = $this->rules->get($result) ?? $this->fail(
                $code,
                'google.results[' . $index . ']',
                'the code of a requirement rule'
            );
            $statuses[$rule->getGoogleStatus()->value] = true;
        }

        return match (true) {
            isset($statuses[GoogleStatus::Ok->value]) => GoogleStatus::Ok,
            isset($statuses[GoogleStatus::Gone->value]) => GoogleStatus::Gone,
            default => GoogleStatus::None,
        };
    }

    /**
     * The questions of a definition
     *
     * @param string $code
     * @param mixed $entries
     * @return list<LibraryQuestionInterface>
     * @throws MalformedLibraryTemplateException
     */
    private function questions(string $code, mixed $entries): array
    {
        if (!is_array($entries) || !array_is_list($entries)) {
            $this->fail($code, 'questions', 'a list');
        }
        $questions = [];
        foreach ($entries as $index => $entry) {
            $key = 'questions[' . $index . ']';
            if (!is_array($entry) || array_is_list($entry)) {
                $this->fail($code, $key, 'an object');
            }
            $this->refuseUnknownKeys($code, $key . '.', $entry, self::QUESTION_KEYS);
            $questionCode = $this->string($code, $key . '.code', $entry['code'] ?? null);
            if (isset($questions[$questionCode])) {
                $this->fail($code, $key . '.code', 'a code no other question has');
            }
            $suggester = $this->string($code, $key . '.suggester', $entry['suggester'] ?? null);
            if (!$this->suggesters->has($suggester)) {
                $this->fail($code, $key . '.suggester', 'the code of a suggester in the suggester pool');
            }
            try {
                $questions[$questionCode] = $this->factory->question(
                    $questionCode,
                    $this->string($code, $key . '.label', $entry['label'] ?? null),
                    $suggester,
                    $this->string($code, $key . '.root', $entry['root'] ?? self::DEFAULT_ROOT)
                );
            } catch (\InvalidArgumentException $e) {
                $this->fail($code, $key, 'a code and a root that are lower-case identifiers');
            }
        }

        return array_values($questions);
    }

    /**
     * The placement of a page template; none for a building block
     *
     * @param string $code
     * @param bool $block
     * @param mixed $placement
     * @return array{list<string>, ConditionTreeInterface}
     * @throws MalformedLibraryTemplateException
     */
    private function placement(string $code, bool $block, mixed $placement): array
    {
        if ($block) {
            if ($placement !== null) {
                $this->fail($code, 'placement', 'absent: a building block has no placement');
            }

            return [[], $this->parser->parse('')];
        }
        if (!is_array($placement) || array_is_list($placement)) {
            $this->fail($code, 'placement', 'an object');
        }
        $this->refuseUnknownKeys($code, 'placement.', $placement, self::PLACEMENT_KEYS);
        $kinds = $this->strings($code, 'placement.pageKinds', $placement['pageKinds'] ?? null);
        if ($kinds === []) {
            $this->fail($code, 'placement.pageKinds', 'a list of at least one kind of page');
        }
        foreach ($kinds as $index => $kind) {
            if (!$this->vocabulary->hasPageKind($kind)) {
                $this->fail($code, 'placement.pageKinds[' . $index . ']', 'the code of a kind of page');
            }
        }
        return [$kinds, $this->conditionsOf($code, $kinds, $placement['conditions'] ?? [])];
    }

    /**
     * The conditions of a placement, the expression builder's tree
     *
     * Each condition must be on a field offered for one of the placement's kinds of page.
     *
     * @param string $code
     * @param list<string> $kinds
     * @param mixed $tree
     * @return ConditionTreeInterface
     * @throws MalformedLibraryTemplateException
     */
    private function conditionsOf(string $code, array $kinds, mixed $tree): ConditionTreeInterface
    {
        if (!is_array($tree) || ($tree !== [] && array_is_list($tree))) {
            $this->fail($code, 'placement.conditions', 'the expression builder\'s tree, an object');
        }
        try {
            $conditions = $this->parser->parseDecoded($tree);
        } catch (SnippetRuleException $e) {
            $this->fail($code, 'placement.conditions', 'conditions that read (' . $e->getReason()->value . ')');
        }
        foreach ($conditions->getLeaves() as $index => $leaf) {
            if (!in_array($this->vocabulary->pageKindOfField($leaf->getField()), $kinds, true)) {
                $this->fail(
                    $code,
                    'placement.conditions[' . $index . ']',
                    'a condition field offered for one of the kinds of page'
                );
            }
        }

        return $conditions;
    }

    /**
     * Check the template: its root type, the context of a building block, its placeholders and its includes
     *
     * @param string $code
     * @param array<mixed> $template
     * @param bool $block
     * @param list<string> $uses
     * @param list<LibraryQuestionInterface> $questions
     * @return void
     * @throws MalformedLibraryTemplateException
     */
    private function checkTemplate(string $code, array $template, bool $block, array $uses, array $questions): void
    {
        if ($block && array_key_exists(self::CONTEXT, $template)) {
            $this->fail($code, 'template.@context', 'absent: a building block is written inside another snippet');
        }
        $broken = false;
        $includes = [];
        array_walk_recursive($template, function (mixed $value, mixed $key) use (&$broken, &$includes): void {
            if (!is_string($value)) {
                return;
            }
            $broken = $broken || $this->substitution->looksLikeBrokenPlaceholder($value);
            if ($key === self::INCLUDE) {
                $includes[$value] = true;
            }
        });
        if ($broken) {
            $this->fail($code, 'template', 'placeholders written exactly as {{? code }}');
        }
        $asked = array_map(static fn (LibraryQuestionInterface $question): string => $question->getCode(), $questions);
        $placeholders = $this->substitution->placeholders($template);
        if (array_diff($asked, $placeholders) !== [] || array_diff($placeholders, $asked) !== []) {
            $this->fail($code, 'template', 'one placeholder per question, and none for a question not asked');
        }
        $included = array_map('strval', array_keys($includes));
        if (array_diff($included, $uses) !== [] || array_diff($uses, $included) !== []) {
            $this->fail($code, 'uses', 'the library codes of exactly the building blocks the template includes');
        }
    }

    /**
     * The first schema.org type of the template's root
     *
     * @param string $code
     * @param array<mixed> $template
     * @return string
     * @throws MalformedLibraryTemplateException
     */
    private function rootType(string $code, array $template): string
    {
        $type = $template[self::TYPE] ?? null;
        $types = is_array($type) && array_is_list($type) ? $type : [$type];
        foreach ($types as $entry) {
            if (!is_string($entry) || preg_match(SnippetInterface::TYPE_PATTERN, $entry) !== 1) {
                $this->fail($code, 'template.@type', 'a schema.org type, or a list of them');
            }
        }

        return (string) $types[0];
    }

    /**
     * A list of non-empty strings
     *
     * @param string $code
     * @param string $key
     * @param mixed $value
     * @return list<string>
     * @throws MalformedLibraryTemplateException
     */
    private function strings(string $code, string $key, mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            $this->fail($code, $key, 'a list of strings');
        }
        $strings = [];
        foreach ($value as $index => $entry) {
            $strings[] = $this->string($code, $key . '[' . $index . ']', $entry);
        }
        if (count(array_unique($strings)) !== count($strings)) {
            $this->fail($code, $key, 'a list without repeats');
        }

        return $strings;
    }

    /**
     * A value that must be a non-empty string
     *
     * @param string $code
     * @param string $key
     * @param mixed $value
     * @return string
     * @throws MalformedLibraryTemplateException
     */
    private function string(string $code, string $key, mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            $this->fail($code, $key, 'a non-empty string');
        }

        return $value;
    }

    /**
     * A string a definition may leave out, which is empty then
     *
     * @param string $code
     * @param string $key
     * @param mixed $value
     * @return string
     * @throws MalformedLibraryTemplateException when it is given and is not a string
     */
    private function optionalString(string $code, string $key, mixed $value): string
    {
        if (!is_string($value)) {
            $this->fail($code, $key, 'a string');
        }

        return $value;
    }

    /**
     * Refuse keys the shape does not have
     *
     * @param string $code
     * @param string $prefix
     * @param array<mixed> $object
     * @param list<string> $known
     * @return void
     * @throws MalformedLibraryTemplateException
     */
    private function refuseUnknownKeys(string $code, string $prefix, array $object, array $known): void
    {
        foreach (array_keys($object) as $key) {
            if (!in_array($key, $known, true)) {
                throw new MalformedLibraryTemplateException(
                    sprintf('The library template "%s" has an unknown key "%s".', $code, $prefix . $key)
                );
            }
        }
    }

    /**
     * Refuse the definition at a key
     *
     * @param string $code
     * @param string $key
     * @param string $expected
     * @return never
     * @throws MalformedLibraryTemplateException
     */
    private function fail(string $code, string $key, string $expected): never
    {
        throw new MalformedLibraryTemplateException(
            sprintf('The library template "%s" is malformed at "%s": expected %s.', $code, $key, $expected)
        );
    }
}
