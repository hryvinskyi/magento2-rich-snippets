<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Editor;

use Hryvinskyi\RichSnippets\Api\Data\RequirementInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;
use Hryvinskyi\RichSnippets\Api\ResultTypeRulePoolInterface;

/**
 * The properties offered when a property is added to an object, for each schema.org type
 *
 * A type the container configuration lists gets its own list: the names, in order, each with the field a value of
 * that name is taken from when one fits (`material` from `product.material`); another module adds a type or names
 * in its own di.xml. Such a list is offered for any object of the type, wherever it sits, so it is keyed by the
 * object's own path, the empty path.
 *
 * A property named by hand takes its field the same way: the fields by name are those of the listed names and those
 * configured for names that are not offered (`color` from `product.color`), so a name typed in gets its field
 * whether or not it is suggested.
 *
 * A type not listed falls back to what the requirement rules ask for: for each type a rule applies to, the names
 * it asks for in each object of the block. A path names one property in each object on its way (`offers.price`
 * asks for `offers` at the root and for `price` in it); a rule applied inside another type (a return policy in a
 * shop) asks for its names inside that object. A name any rule requires there is marked required; an any-of group
 * is offered by the path that stands for it. The editor leaves out the names the object already has.
 *
 * A malformed configured list is a configuration error, refused when the suggestions are built.
 */
class PropertySuggestions
{
    private const TYPE_PATTERN = '/\A[A-Z][A-Za-z0-9]*\z/';
    private const NAME_PATTERN = '/\A@?[A-Za-z][A-Za-z0-9_]*\z/';
    private const FIELD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)+\z/';
    private const ROOT = '';

    /**
     * @var array<string, list<array{name: string, required: bool, field: string|null}>>
     */
    private array $configured = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $namedFields = [];

    /**
     * @param ResultTypeRulePoolInterface $rules
     * @param array<array-key,mixed> $suggestions by type, each a list of `{"name", "field"}` keyed as the
     *     container configuration writes them; `field` may be left out
     * @param array<array-key,mixed> $fields by type, the field a property named by hand is taken from, by name, for
     *     names the type's list does not offer
     * @throws \InvalidArgumentException when a type, a name or a field is malformed
     */
    public function __construct(
        private readonly ResultTypeRulePoolInterface $rules,
        array $suggestions = [],
        array $fields = []
    ) {
        foreach ($suggestions as $type => $entries) {
            $type = (string) $type;
            if (preg_match(self::TYPE_PATTERN, $type) !== 1 || !is_array($entries)) {
                throw new \InvalidArgumentException(sprintf('The suggestions for "%s" are malformed.', $type));
            }
            $this->configured[$type] = [];
            foreach ($entries as $entry) {
                $suggestion = $this->entry($type, $entry);
                $this->configured[$type][] = $suggestion;
                if ($suggestion['field'] !== null) {
                    $this->namedFields[$type][$suggestion['name']] = $suggestion['field'];
                }
            }
        }
        foreach ($fields as $type => $byName) {
            $type = (string) $type;
            if (preg_match(self::TYPE_PATTERN, $type) !== 1 || !is_array($byName)) {
                throw new \InvalidArgumentException(sprintf('The fields by name for "%s" are malformed.', $type));
            }
            foreach ($byName as $name => $field) {
                $this->namedFields[$type][(string) $name] = $this->namedField($type, (string) $name, $field);
            }
        }
    }

    /**
     * The field a property is taken from when it is given a name, by type, then by name
     *
     * @return array<string, array<string, string>>
     */
    public function fieldsByName(): array
    {
        return $this->namedFields;
    }

    /**
     * The names offered, by type, then by the path of the object inside the block (`` the object itself)
     *
     * @return array<string, array<string, list<array{name: string, required: bool, field: string|null}>>>
     * @throws MalformedRuleException when a rule definition is malformed
     */
    public function byType(): array
    {
        $suggestions = [];
        foreach ($this->fromRules() as $type => $objects) {
            if (!isset($this->configured[$type])) {
                $suggestions[$type] = $objects;
            }
        }
        foreach ($this->configured as $type => $entries) {
            $suggestions[$type] = [self::ROOT => $entries];
        }

        return $suggestions;
    }

    /**
     * What the requirement rules ask for, by type, then by object path
     *
     * @return array<string, array<string, list<array{name: string, required: bool, field: string|null}>>>
     * @throws MalformedRuleException
     */
    private function fromRules(): array
    {
        $asked = [];
        foreach ($this->rules->all() as $rule) {
            foreach ($rule->getAnchors() as $type => $anchor) {
                $this->collect($asked, (string) $type, $anchor, $rule->getRequired(), true);
                $this->collect($asked, (string) $type, $anchor, $rule->getRecommended(), false);
            }
        }
        $suggestions = [];
        foreach ($asked as $type => $objects) {
            foreach ($objects as $object => $names) {
                $required = array_filter($names);
                $listed = array_map('strval', array_keys($required + $names));
                $suggestions[$type][$object] = array_map(
                    static fn (string $name): array => [
                        'name' => $name,
                        'required' => isset($required[$name]),
                        'field' => null,
                    ],
                    $listed
                );
            }
        }

        return $suggestions;
    }

    /**
     * Note the names some requirements ask for in each object, keeping a name required once any rule requires it
     *
     * @param array<string,array<string,array<string,bool>>> $asked
     * @param string $type
     * @param string $anchor
     * @param list<RequirementInterface> $requirements
     * @param bool $required
     * @return void
     */
    private function collect(array &$asked, string $type, string $anchor, array $requirements, bool $required): void
    {
        foreach ($requirements as $requirement) {
            $object = $anchor;
            foreach (explode('.', $requirement->getName()) as $segment) {
                $asked[$type][$object][$segment] = ($asked[$type][$object][$segment] ?? false) || $required;
                $object = $object === self::ROOT ? $segment : $object . '.' . $segment;
            }
        }
    }

    /**
     * One configured suggestion
     *
     * @param string $type
     * @param mixed $entry
     * @return array{name: string, required: bool, field: string|null}
     * @throws \InvalidArgumentException
     */
    private function entry(string $type, mixed $entry): array
    {
        $name = is_array($entry) ? ($entry['name'] ?? null) : null;
        $field = is_array($entry) ? ($entry['field'] ?? null) : null;
        if (!is_string($name) || preg_match(self::NAME_PATTERN, $name) !== 1
            || ($field !== null && (!is_string($field) || preg_match(self::FIELD_PATTERN, $field) !== 1))
        ) {
            throw new \InvalidArgumentException(sprintf('A suggestion for "%s" is malformed.', $type));
        }

        return ['name' => $name, 'required' => false, 'field' => $field];
    }

    /**
     * One configured field of a name
     *
     * @param string $type
     * @param string $name
     * @param mixed $field
     * @return string
     * @throws \InvalidArgumentException
     */
    private function namedField(string $type, string $name, mixed $field): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || !is_string($field)
            || preg_match(self::FIELD_PATTERN, $field) !== 1
        ) {
            throw new \InvalidArgumentException(sprintf('A field by name for "%s" is malformed.', $type));
        }

        return $field;
    }
}
