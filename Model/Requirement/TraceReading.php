<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

use Hryvinskyi\JsonTemplate\Api\Data\TraceOutcome;
use Hryvinskyi\JsonTemplate\Api\RecordingTraceInterface;

/**
 * The properties an evaluation left out, by dot path, read from its recording trace
 *
 * A trace entry's JSON path (`$.review[2].author`, `$['odd key']`) becomes a dot path with list indexes dropped
 * (`review.author`); the entries of one path across list items merge, their source variables united in the order
 * read. Only empty and skipped nodes are kept, in the order the trace recorded them.
 *
 * @phpstan-type LeftOutEntries array<string, array{outcome: TraceOutcome, sources: list<string>}>
 */
class TraceReading
{
    /**
     * The left-out properties of a trace, by dot path
     *
     * @param RecordingTraceInterface $trace
     * @return LeftOutEntries
     */
    public function leftOut(RecordingTraceInterface $trace): array
    {
        $entries = [];
        foreach ($trace->getEntries() as $entry) {
            $outcome = $entry->getOutcome();
            if ($outcome === TraceOutcome::Value) {
                continue;
            }
            $path = $this->dotPath($entry->getPath());
            if ($path === null || $path === '') {
                continue;
            }
            $known = $entries[$path] ?? ['outcome' => $outcome, 'sources' => []];
            $known['sources'] = array_values(array_unique([...$known['sources'], ...$entry->getSources()]));
            $entries[$path] = $known;
        }

        return $entries;
    }

    /**
     * The left-out properties no other left-out property contains: what a person sees missing
     *
     * @param LeftOutEntries $entries
     * @return LeftOutEntries
     */
    public function outermost(array $entries): array
    {
        return array_filter(
            $entries,
            fn (string $path): bool => $this->ancestor($entries, $path) === null,
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * The variables the template read for a property it left out
     *
     * The property's own entry first; without sources there, those of the properties inside it; without any, those
     * of the nearest left-out property around it (a condition that skipped the whole object).
     *
     * @param LeftOutEntries $entries
     * @param string $path
     * @return list<string>
     */
    public function sourcesFor(array $entries, string $path): array
    {
        $own = $entries[$path]['sources'] ?? [];
        if ($own !== []) {
            return $own;
        }
        $inside = [];
        foreach ($entries as $candidate => $entry) {
            if (str_starts_with($candidate, $path . '.')) {
                $inside = [...$inside, ...$entry['sources']];
            }
        }
        if ($inside !== []) {
            return array_values(array_unique($inside));
        }
        $ancestor = $this->ancestor($entries, $path);

        return $ancestor === null ? [] : $entries[$ancestor]['sources'];
    }

    /**
     * The nearest left-out path that contains a path
     *
     * @param LeftOutEntries $entries
     * @param string $path
     * @return string|null
     */
    private function ancestor(array $entries, string $path): ?string
    {
        $cut = strrpos($path, '.');
        while ($cut !== false) {
            $path = substr($path, 0, $cut);
            if (isset($entries[$path])) {
                return $path;
            }
            $cut = strrpos($path, '.');
        }

        return null;
    }

    /**
     * The dot path of a JSON path, list indexes dropped; null when the JSON path cannot be read
     *
     * @param string $jsonPath
     * @return string|null
     */
    private function dotPath(string $jsonPath): ?string
    {
        if (!str_starts_with($jsonPath, '$')) {
            return null;
        }
        preg_match_all(
            '/\G(?:\.(@?[A-Za-z_][A-Za-z0-9_]*)|\[(\d+)\]|\[\'((?:[^\'\\\\]|\\\\.)*)\'\])/',
            substr($jsonPath, 1),
            $matches,
            PREG_SET_ORDER
        );
        $names = [];
        $read = 1;
        foreach ($matches as $match) {
            $read += strlen($match[0]);
            $quoted = isset($match[3]) ? (string) preg_replace('/\\\\(.)/s', '$1', $match[3]) : null;
            $name = ($match[1] ?? '') !== '' ? $match[1] : $quoted;
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $read === strlen($jsonPath) ? implode('.', $names) : null;
    }
}
