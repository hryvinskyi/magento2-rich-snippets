<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * How many checked blocks have what one kind of Google result needs
 *
 * @api
 */
interface FeatureTallyInterface
{
    /**
     * The rule of the result
     *
     * @return ResultTypeRuleInterface
     */
    public function getRule(): ResultTypeRuleInterface;

    /**
     * The blocks that meet the result's requirements
     *
     * @return int
     */
    public function getMet(): int;

    /**
     * The blocks that miss a required property
     *
     * @return int
     */
    public function getNotMet(): int;

    /**
     * The blocks that leave the result out (review stars of products with no reviews)
     *
     * @return int
     */
    public function getLeftOut(): int;

    /**
     * Every block counted
     *
     * @return int
     */
    public function getTotal(): int;

    /**
     * No result when Google does not show the result; partly when a block misses a required property; else met
     *
     * @return TallyStatus
     */
    public function getStatus(): TallyStatus;
}
