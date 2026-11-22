<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Magento\Framework\Cache\CacheConstants;
use Magento\Framework\Cache\FrontendInterface;

/**
 * A cache frontend kept in memory, with a hook that runs just before an entry is saved
 *
 * The hook lets a test put another process's work between two steps of the code under test, deterministically.
 */
class InMemoryCache implements FrontendInterface
{
    /**
     * @var array<string, array{data: string, tags: list<string>}>
     */
    private array $entries = [];

    /**
     * @var array<string, \Closure(): void>
     */
    private array $beforeSave = [];

    /**
     * Run a callback once, just before the entry with this identifier is next saved
     *
     * @param string $identifier
     * @param \Closure(): void $callback
     * @return void
     */
    public function beforeSaving(string $identifier, \Closure $callback): void
    {
        $this->beforeSave[$identifier] = $callback;
    }

    /**
     * @inheritDoc
     */
    public function test($identifier)
    {
        return isset($this->entries[$identifier]) ? 1 : false;
    }

    /**
     * @inheritDoc
     */
    public function load($identifier)
    {
        return $this->entries[$identifier]['data'] ?? false;
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $tags
     */
    public function save($data, $identifier, array $tags = [], $lifeTime = null)
    {
        $hook = $this->beforeSave[$identifier] ?? null;
        unset($this->beforeSave[$identifier]);
        if ($hook !== null) {
            $hook();
        }
        $this->entries[$identifier] = [
            'data' => (string) $data,
            'tags' => array_values(
                array_map(static fn (mixed $tag): string => is_scalar($tag) ? (string) $tag : '', $tags)
            ),
        ];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function remove($identifier)
    {
        unset($this->entries[$identifier]);

        return true;
    }

    /**
     * The identifiers of the entries kept, in the order they were first saved
     *
     * @return list<string>
     */
    public function identifiers(): array
    {
        return array_map('strval', array_keys($this->entries));
    }

    /**
     * @inheritDoc
     *
     * @param array<mixed> $tags
     */
    public function clean($mode = CacheConstants::CLEANING_MODE_ALL, array $tags = [])
    {
        if ($mode === CacheConstants::CLEANING_MODE_ALL) {
            $this->entries = [];

            return true;
        }
        foreach ($this->entries as $identifier => $entry) {
            if (array_intersect($entry['tags'], array_filter($tags, 'is_string')) !== []) {
                unset($this->entries[$identifier]);
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getBackend()
    {
        throw new \BadMethodCallException('Not used by the code under test.');
    }

    /**
     * @inheritDoc
     */
    public function getLowLevelFrontend()
    {
        throw new \BadMethodCallException('Not used by the code under test.');
    }
}
