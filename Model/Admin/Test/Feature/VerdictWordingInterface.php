<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Feature;

use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;

/**
 * Words what the "Against Google’s requirements" panel says about a kind of result under one verdict
 */
interface VerdictWordingInterface
{
    /**
     * What the entry says
     *
     * @param ResultTypeRuleInterface $rule the kind of result
     * @param list<string> $missingRecommended the paths of the recommended properties the page does not have
     * @return string empty when the entry says nothing
     */
    public function describe(ResultTypeRuleInterface $rule, array $missingRecommended): string;
}
