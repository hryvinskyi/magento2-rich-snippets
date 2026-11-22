<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config;

use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Keeps the decoded definitions a pool reads from module files in the module's cache type
 *
 * One entry holds every definition of a pool, under a key that changes with the set of codes, so a module that adds
 * or drops a definition never reads an entry built without it. An entry that is not one object per code reads as
 * nothing kept. The pool validates what it reads before it keeps it.
 */
class KeptDefinitions
{
    private const KEY_PREFIX = 'rich_snippets_definitions_';
    private const TAG_PREFIX = 'rich_snippets_definitions_';

    /**
     * @param FrontendInterface $cache the module's cache type
     * @param SerializerInterface $json
     */
    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly SerializerInterface $json
    ) {
    }

    /**
     * The definitions kept for a pool and a set of codes; null when none are
     *
     * @param string $pool the pool's name, unique among the module's pools
     * @param list<string> $codes
     * @return array<string, array<mixed>>|null by code, in the order of the codes
     */
    public function load(string $pool, array $codes): ?array
    {
        $stored = $this->cache->load($this->key($pool, $codes));
        if (!is_string($stored) || $stored === '') {
            return null;
        }
        try {
            $kept = $this->json->unserialize($stored);
        } catch (\InvalidArgumentException) {
            return null;
        }
        if (!is_array($kept)) {
            return null;
        }
        $definitions = [];
        foreach ($codes as $code) {
            $definition = $kept[$code] ?? null;
            if (!is_array($definition)) {
                return null;
            }
            $definitions[$code] = $definition;
        }

        return $definitions;
    }

    /**
     * Keep a pool's validated definitions
     *
     * @param string $pool
     * @param array<string,array<mixed>> $definitions by code
     * @return void
     */
    public function keep(string $pool, array $definitions): void
    {
        $this->cache->save(
            (string) $this->json->serialize($definitions),
            $this->key($pool, array_map('strval', array_keys($definitions))),
            [self::TAG_PREFIX . $pool]
        );
    }

    /**
     * The entry's key
     *
     * @param string $pool
     * @param list<string> $codes
     * @return string
     */
    private function key(string $pool, array $codes): string
    {
        return self::KEY_PREFIX . $pool . '_' . hash('xxh128', implode(',', $codes));
    }
}
