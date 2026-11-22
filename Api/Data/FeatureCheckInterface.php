<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How one block stands against one kind of Google result
 *
 * @api
 */
interface FeatureCheckInterface
{
    /**
     * The rule of the result
     *
     * @return ResultTypeRuleInterface
     */
    public function getRule(): ResultTypeRuleInterface;

    /**
     * Met, not met or left out
     *
     * @return FeatureVerdict
     */
    public function getVerdict(): FeatureVerdict;

    /**
     * The required properties the block misses; none when the result is left out because its object is not there
     *
     * @return list<MissingPropertyInterface>
     */
    public function getMissingRequired(): array;

    /**
     * The recommended properties the block misses; none when the result is left out because its object is not there
     *
     * @return list<MissingPropertyInterface>
     */
    public function getMissingRecommended(): array;
}
