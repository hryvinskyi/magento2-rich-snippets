<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront;

/**
 * The places a page can write its script blocks: one entry per value of the position setting, in the order the
 * setting offers them
 *
 * An entry is `{"writer", "label", "place", "line", "note"}`: the writer of the blocks, the option the setting shows
 * ("In the head"), the words a sentence names the place by ("in the head"), the whole sentence the Snippets page says
 * about a snippet that is on ("Each snippet that is on writes one JSON-LD script block into the head of the pages it
 * is set for.") and the note of the editor's preview ("One script block in the page head"), all in English,
 * translated where they are shown. Another module adds a place with one entry. An entry that
 * lacks one of them is an error in the configuration, refused when the pool is built; a position nobody writes fails
 * loudly.
 *
 * @phpstan-type Position array{writer: OutputPositionInterface, label: string, place: string, line: string,
 *     note: string}
 */
class OutputPositionPool
{
    private const CODE = '/\A[a-z][a-z0-9_]{0,31}\z/';
    private const TEXTS = ['label', 'place', 'line', 'note'];

    /**
     * @var array<string, Position>
     */
    private array $positions = [];

    /**
     * @param array<array-key,mixed> $positions by the position's setting value
     * @throws \InvalidArgumentException when an entry is malformed
     */
    public function __construct(array $positions = [])
    {
        foreach ($positions as $code => $position) {
            $code = (string) $code;
            $writer = is_array($position) ? ($position['writer'] ?? null) : null;
            if (preg_match(self::CODE, $code) !== 1 || !is_array($position)
                || !$writer instanceof OutputPositionInterface
            ) {
                throw new \InvalidArgumentException(sprintf('The output position "%s" is malformed.', $code));
            }
            $texts = [];
            foreach (self::TEXTS as $key) {
                $text = $position[$key] ?? null;
                if (!is_string($text) || $text === '') {
                    throw new \InvalidArgumentException(
                        sprintf('The output position "%s" has no %s.', $code, $key)
                    );
                }
                $texts[$key] = $text;
            }
            $this->positions[$code] = [
                'writer' => $writer,
                'label' => $texts['label'],
                'place' => $texts['place'],
                'line' => $texts['line'],
                'note' => $texts['note'],
            ];
        }
    }

    /**
     * The setting values of the positions, in the order the setting offers them
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map('strval', array_keys($this->positions));
    }

    /**
     * Whether a position has the setting value
     *
     * @param string $code
     * @return bool
     */
    public function has(string $code): bool
    {
        return isset($this->positions[$code]);
    }

    /**
     * The writer of a position
     *
     * @param string $code the position's setting value
     * @return OutputPositionInterface
     * @throws \OutOfBoundsException when no position has the value
     */
    public function get(string $code): OutputPositionInterface
    {
        return $this->position($code)['writer'];
    }

    /**
     * The option the setting shows for a position, in English
     *
     * @param string $code
     * @return string
     * @throws \OutOfBoundsException when no position has the value
     */
    public function label(string $code): string
    {
        return $this->position($code)['label'];
    }

    /**
     * The words a sentence names a position by ("in the head"), in English
     *
     * @param string $code
     * @return string
     * @throws \OutOfBoundsException when no position has the value
     */
    public function place(string $code): string
    {
        return $this->position($code)['place'];
    }

    /**
     * What the Snippets page says about a snippet that is on when every store view writes at this position, in English
     *
     * @param string $code
     * @return string
     * @throws \OutOfBoundsException when no position has the value
     */
    public function line(string $code): string
    {
        return $this->position($code)['line'];
    }

    /**
     * The note of the editor's preview when every store view writes at this position, in English
     *
     * @param string $code
     * @return string
     * @throws \OutOfBoundsException when no position has the value
     */
    public function note(string $code): string
    {
        return $this->position($code)['note'];
    }

    /**
     * One entry
     *
     * @param string $code
     * @return Position
     * @throws \OutOfBoundsException when no position has the value
     */
    private function position(string $code): array
    {
        return $this->positions[$code]
            ?? throw new \OutOfBoundsException('No writer is registered for the position "' . $code . '".');
    }
}
