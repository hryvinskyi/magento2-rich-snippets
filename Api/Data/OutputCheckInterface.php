<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * One evaluated block compared with what Google publishes for the kinds of result its types can have
 *
 * @api
 */
interface OutputCheckInterface
{
    /**
     * The block's schema.org type, as the check was asked for
     *
     * @return string
     */
    public function getType(): string;

    /**
     * One entry per kind of result the block's types can have, in the order of the rules
     *
     * None for a type Google has no result for.
     *
     * @return list<FeatureCheckInterface>
     */
    public function getFeatures(): array;

    /**
     * Whether any rule applies to the block's types
     *
     * @return bool
     */
    public function hasRule(): bool;

    /**
     * The properties the template writes that have no value on the page, outermost first, in template order
     *
     * Known only when the block was evaluated with a recording trace; empty otherwise.
     *
     * @return list<LeftOutPropertyInterface>
     */
    public function getLeftOut(): array;
}
