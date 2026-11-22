<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

use Hryvinskyi\RichSnippets\Api\Data\GoogleStatus;
use Hryvinskyi\RichSnippets\Api\Data\RequirementInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;
use Hryvinskyi\RichSnippets\Api\ResultTypeRuleSourceInterface;

/**
 * Turns a decoded rule definition into a rule, refusing any definition that does not have a rule's shape
 *
 * The shape is the one ResultTypeRuleSourceInterface describes. An unknown key, a value of the wrong type, a
 * Google status outside `ok`, `none` and `gone`, a type named twice or a property path of another shape each fail
 * with a message that names the rule's code and the key.
 */
class RuleDefinitionParser
{
    private const KEYS = ['label', 'phrase', 'appliesTo', 'optional', 'googleStatus', 'required', 'recommended',
        'texts'];
    private const APPLICATION_KEYS = ['type', 'at'];
    private const TEXT_KEYS = ['requirement', 'met', 'leftOut', 'status'];
    private const ANY_OF = 'anyOf';
    private const PATH = 'path';
    private const MISSING = 'missing';
    private const WHY = 'why';
    private const ENTRY_KEYS = [self::PATH, self::ANY_OF, self::MISSING, self::WHY];

    /**
     * @param RuleFactoryInterface $factory
     */
    public function __construct(private readonly RuleFactoryInterface $factory)
    {
    }

    /**
     * The rule a definition describes
     *
     * @param string $code the rule's code, its key in the pool
     * @param array<mixed> $definition
     * @return ResultTypeRuleInterface
     * @throws MalformedRuleException when the definition is malformed
     * @see ResultTypeRuleSourceInterface for the shape
     */
    public function parse(string $code, array $definition): ResultTypeRuleInterface
    {
        $this->refuseUnknownKeys($code, '', $definition, self::KEYS);
        $texts = $definition['texts'] ?? null;
        if (!is_array($texts)) {
            $this->fail($code, 'texts', 'an object');
        }
        $this->refuseUnknownKeys($code, 'texts.', $texts, self::TEXT_KEYS);
        $status = GoogleStatus::tryFrom($this->string($code, 'googleStatus', $definition['googleStatus'] ?? 'ok'));
        if ($status === null) {
            $this->fail($code, 'googleStatus', 'one of "ok", "none" and "gone"');
        }
        $optional = $definition['optional'] ?? false;
        if (!is_bool($optional)) {
            $this->fail($code, 'optional', 'true or false');
        }

        return $this->factory->rule(
            $code,
            $this->string($code, 'label', $definition['label'] ?? null),
            $this->string($code, 'phrase', $definition['phrase'] ?? null),
            $this->anchors($code, $definition['appliesTo'] ?? null),
            $optional,
            $status,
            $this->requirements($code, 'required', $definition['required'] ?? []),
            $this->requirements($code, 'recommended', $definition['recommended'] ?? []),
            $this->string($code, 'texts.requirement', $texts['requirement'] ?? null),
            $this->string($code, 'texts.met', $texts['met'] ?? null),
            $this->string($code, 'texts.leftOut', $texts['leftOut'] ?? ''),
            $this->string($code, 'texts.status', $texts['status'] ?? '')
        );
    }

    /**
     * The anchor of each type the rule applies to
     *
     * @param string $code
     * @param mixed $applications
     * @return array<string, string>
     * @throws MalformedRuleException
     */
    private function anchors(string $code, mixed $applications): array
    {
        if (!is_array($applications) || !array_is_list($applications) || $applications === []) {
            $this->fail($code, 'appliesTo', 'a list of at least one type');
        }
        $anchors = [];
        foreach ($applications as $index => $application) {
            $key = 'appliesTo[' . $index . ']';
            if (!is_array($application)) {
                $this->fail($code, $key, 'an object');
            }
            $this->refuseUnknownKeys($code, $key . '.', $application, self::APPLICATION_KEYS);
            $type = $this->string($code, $key . '.type', $application['type'] ?? null);
            if (isset($anchors[$type])) {
                $this->fail($code, $key . '.type', 'a type not named before');
            }
            $anchors[$type] = $this->string($code, $key . '.at', $application['at'] ?? '');
        }

        return $anchors;
    }

    /**
     * The requirements of a list of paths and any-of groups
     *
     * @param string $code
     * @param string $key
     * @param mixed $entries
     * @return list<RequirementInterface>
     * @throws MalformedRuleException
     */
    private function requirements(string $code, string $key, mixed $entries): array
    {
        if (!is_array($entries) || !array_is_list($entries)) {
            $this->fail($code, $key, 'a list');
        }
        $requirements = [];
        foreach ($entries as $index => $entry) {
            $entryKey = $key . '[' . $index . ']';
            try {
                $requirements[] = $this->factory->requirement(
                    $this->paths($code, $entryKey, $entry),
                    $this->phrase($code, $entryKey, $entry, self::MISSING),
                    $this->phrase($code, $entryKey, $entry, self::WHY)
                );
            } catch (MalformedRuleException $e) {
                throw new MalformedRuleException(
                    sprintf('The rule "%s" is malformed at "%s": %s', $code, $entryKey, $e->getMessage()),
                    0,
                    $e
                );
            }
        }

        return $requirements;
    }

    /**
     * The paths of one entry: a path, or the members of an any-of group
     *
     * Each is written alone or as an object, which may also give the entry its title.
     *
     * @param string $code
     * @param string $key
     * @param mixed $entry
     * @return list<string>
     * @throws MalformedRuleException
     */
    private function paths(string $code, string $key, mixed $entry): array
    {
        if (is_string($entry)) {
            return [$entry];
        }
        if (!is_array($entry)) {
            $this->fail($code, $key, 'a property path, {"path": path} or {"anyOf": [paths]}');
        }
        $this->refuseUnknownKeys($code, $key . '.', $entry, self::ENTRY_KEYS);
        if (array_key_exists(self::PATH, $entry) === array_key_exists(self::ANY_OF, $entry)) {
            $this->fail($code, $key, 'either "path" or "anyOf"');
        }
        if (array_key_exists(self::PATH, $entry)) {
            return [$this->string($code, $key . '.' . self::PATH, $entry[self::PATH])];
        }
        $members = $entry[self::ANY_OF];
        if (!is_array($members) || !array_is_list($members) || count($members) < 2) {
            $this->fail($code, $key . '.' . self::ANY_OF, 'a list of at least two property paths');
        }

        return array_map(
            fn (mixed $member): string => $this->string($code, $key . '.' . self::ANY_OF, $member),
            $members
        );
    }

    /**
     * A phrase an entry gives itself: its title, or why it is worth adding
     *
     * Empty for an entry written as a bare path or group, or one that gives none.
     *
     * @param string $code
     * @param string $key
     * @param mixed $entry
     * @param string $name
     * @return string
     * @throws MalformedRuleException
     */
    private function phrase(string $code, string $key, mixed $entry, string $name): string
    {
        return is_array($entry) ? $this->string($code, $key . '.' . $name, $entry[$name] ?? '') : '';
    }

    /**
     * A value that must be a string
     *
     * @param string $code
     * @param string $key
     * @param mixed $value
     * @return string
     * @throws MalformedRuleException
     */
    private function string(string $code, string $key, mixed $value): string
    {
        if (!is_string($value)) {
            $this->fail($code, $key, 'a string');
        }

        return $value;
    }

    /**
     * Fail on a key the shape does not know, which is most often a typing error in a known one
     *
     * @param string $code
     * @param string $prefix
     * @param array<mixed> $object
     * @param list<string> $known
     * @return void
     * @throws MalformedRuleException
     */
    private function refuseUnknownKeys(string $code, string $prefix, array $object, array $known): void
    {
        foreach (array_keys($object) as $key) {
            if (!in_array($key, $known, true)) {
                throw new MalformedRuleException(
                    sprintf('The rule "%s" has the unknown key "%s%s".', $code, $prefix, $key)
                );
            }
        }
    }

    /**
     * Fail on a value that is not what its key needs
     *
     * @param string $code
     * @param string $key
     * @param string $expected
     * @return never
     * @throws MalformedRuleException
     */
    private function fail(string $code, string $key, string $expected): never
    {
        throw new MalformedRuleException(sprintf('The rule "%s" needs %s at "%s".', $code, $expected, $key));
    }
}
