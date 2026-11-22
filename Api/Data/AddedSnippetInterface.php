<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * A snippet an add from the library ended with: one it created, switched off, or a building block it reused
 *
 * @api
 */
interface AddedSnippetInterface
{
    /**
     * The snippet's ID
     *
     * @return int
     */
    public function getId(): int;

    /**
     * The snippet's code
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * The snippet's name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Whether the snippet is a building block
     *
     * @return bool
     */
    public function isBlock(): bool;

    /**
     * The code of the library template the snippet stands for
     *
     * @return string
     */
    public function getTemplate(): string;

    /**
     * Whether the add reused a building block the shop had rather than creating the snippet
     *
     * @return bool
     */
    public function isReused(): bool;
}
