<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\PageTest;

use Hryvinskyi\RichSnippets\Api\Data\JsonLdBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\TestedBlockInterface;
use Hryvinskyi\RichSnippets\Api\Data\TestedBlockInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\RequirementCheckerInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Attributes the JSON-LD blocks of a page to their snippets and checks them against Google's requirements
 *
 * A block is attributed by the code its script element carries; a block without one, or with a code no snippet has,
 * has no snippet. A block describes its root object, or each object of a root list, or each object of a root
 * `@graph`; each object is checked as its first type says.
 */
class TestedBlocks
{
    private const TYPE = '@type';
    private const GRAPH = '@graph';

    /**
     * @param SnippetRepositoryInterface $snippets
     * @param RequirementCheckerInterface $checker
     * @param TestedBlockInterfaceFactory $testedBlocks
     */
    public function __construct(
        private readonly SnippetRepositoryInterface $snippets,
        private readonly RequirementCheckerInterface $checker,
        private readonly TestedBlockInterfaceFactory $testedBlocks
    ) {
    }

    /**
     * The blocks, each with its snippet and its checks, in page order
     *
     * @param list<JsonLdBlockInterface> $blocks
     * @return list<TestedBlockInterface>
     */
    public function of(array $blocks): array
    {
        $snippets = [];
        $tested = [];
        foreach ($blocks as $block) {
            $code = $block->getCode();
            if ($code !== null && !array_key_exists($code, $snippets)) {
                $snippets[$code] = $this->snippet($code);
            }
            $objects = $this->objectsOf($block->getValue());
            $tested[] = $this->testedBlocks->create([
                'block' => $block,
                'snippet' => $code === null ? null : $snippets[$code],
                'type' => $objects === [] ? '' : $this->typeOf($objects[0]),
                'objects' => $objects,
                'checks' => array_map(
                    fn (array $object) => $this->checker->checkOutput($this->typeOf($object), $object),
                    $objects
                ),
            ]);
        }

        return $tested;
    }

    /**
     * The snippet a code names; null when none has it
     *
     * @param string $code
     * @return SnippetInterface|null
     */
    private function snippet(string $code): ?SnippetInterface
    {
        try {
            return $this->snippets->getByCode($code);
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * The objects a block's value describes
     *
     * @param array<mixed>|null $value
     * @return list<array<mixed>>
     */
    private function objectsOf(?array $value): array
    {
        if ($value === null) {
            return [];
        }
        $graph = $value[self::GRAPH] ?? null;
        $items = match (true) {
            array_is_list($value) => $value,
            is_array($graph) && array_is_list($graph) => $graph,
            default => [$value],
        };

        return array_values(array_filter(
            $items,
            static fn (mixed $item): bool => is_array($item) && $item !== [] && !array_is_list($item)
        ));
    }

    /**
     * The first type an object names; empty when it names none
     *
     * @param array<mixed> $object
     * @return string
     */
    private function typeOf(array $object): string
    {
        $type = $object[self::TYPE] ?? null;
        $first = is_array($type) ? (array_values($type)[0] ?? null) : $type;

        return is_string($first) ? $first : '';
    }
}
