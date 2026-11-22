<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;
use Hryvinskyi\RichSnippets\Api\ResultTypeRulePoolInterface;
use Hryvinskyi\RichSnippets\Api\ResultTypeRuleSourceInterface;
use Hryvinskyi\RichSnippets\Model\Config\KeptDefinitions;

/**
 * The rules of the sources in di.xml, read once and kept in the module's cache type
 *
 * The decoded definitions of every source are kept as one entry, under a key that changes with the set of rule
 * codes, so a module that adds or drops a rule never reads an entry built without it. Every definition is validated
 * before the entry is kept and again whenever the rules are built from it, so a malformed definition fails the load
 * and never reaches a check. Within a process the rules are built once.
 */
class ResultTypeRulePool implements ResultTypeRulePoolInterface
{
    private const POOL = 'result_type_rules';

    /**
     * @var array<string, ResultTypeRuleInterface>|null the rules by code, once built
     */
    private ?array $rules = null;

    /**
     * @param RuleDefinitionParser $parser
     * @param KeptDefinitions $kept
     * @param array<string,ResultTypeRuleSourceInterface> $sources by rule code, in the order the rules are listed
     */
    public function __construct(
        private readonly RuleDefinitionParser $parser,
        private readonly KeptDefinitions $kept,
        private readonly array $sources = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return array_values($this->rules());
    }

    /**
     * @inheritDoc
     */
    public function get(string $code): ?ResultTypeRuleInterface
    {
        return $this->rules()[$code] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function forType(string $type): array
    {
        return array_values(array_filter(
            $this->rules(),
            static fn (ResultTypeRuleInterface $rule): bool => $rule->anchorFor($type) !== null
        ));
    }

    /**
     * The rules by code
     *
     * @return array<string, ResultTypeRuleInterface>
     * @throws MalformedRuleException
     */
    private function rules(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }
        $codes = array_map('strval', array_keys($this->sources));
        $definitions = $this->kept->load(self::POOL, $codes);
        if ($definitions !== null) {
            return $this->rules = $this->build($definitions);
        }
        $definitions = $this->read();
        $this->rules = $this->build($definitions);
        $this->kept->keep(self::POOL, $definitions);

        return $this->rules;
    }

    /**
     * The definitions of every source, read from the sources themselves
     *
     * @return array<string, array<mixed>>
     * @throws MalformedRuleException
     */
    private function read(): array
    {
        $definitions = [];
        foreach ($this->sources as $code => $source) {
            $definitions[(string) $code] = $source->read();
        }

        return $definitions;
    }

    /**
     * The rules of the definitions, each validated
     *
     * @param array<string,array<mixed>> $definitions
     * @return array<string, ResultTypeRuleInterface>
     * @throws MalformedRuleException
     */
    private function build(array $definitions): array
    {
        $rules = [];
        foreach ($definitions as $code => $definition) {
            $rules[$code] = $this->parser->parse($code, $definition);
        }

        return $rules;
    }
}
