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
 * A result the page meets: what it has, then either that every recommended value is there too or that the list of
 * those that are not follows
 */
class MetWording implements VerdictWordingInterface
{
    /**
     * @inheritDoc
     */
    public function describe(ResultTypeRuleInterface $rule, array $missingRecommended): string
    {
        return __($rule->getMetText()) . ' ' . ($missingRecommended === []
            ? __('Every recommended value is there too.')
            : __('Recommended and not there:'));
    }
}
