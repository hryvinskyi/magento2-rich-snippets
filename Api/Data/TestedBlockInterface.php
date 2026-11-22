<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One JSON-LD block of a tested page: the snippet that wrote it and how it stands against Google's requirements
 *
 * @api
 */
interface TestedBlockInterface
{
    /**
     * The block as the page carries it
     *
     * @return JsonLdBlockInterface
     */
    public function getBlock(): JsonLdBlockInterface;

    /**
     * The snippet the block's code names; null for a block without a code, or whose code no snippet has
     *
     * @return SnippetInterface|null
     */
    public function getSnippet(): ?SnippetInterface;

    /**
     * The first schema.org type of the first object the block describes; empty when it names none
     *
     * @return string
     */
    public function getType(): string;

    /**
     * The objects the block describes; none for a block whose JSON cannot be read
     *
     * They are its root object, or the objects of a root list, or the objects of its `@graph`.
     *
     * @return list<array<mixed>>
     */
    public function getObjects(): array;

    /**
     * One check per object the block describes, in the order of the objects
     *
     * @return list<OutputCheckInterface>
     */
    public function getChecks(): array;
}
