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
 * A result the page does not try for: why, when its rule says so; nothing otherwise
 */
class LeftOutWording implements VerdictWordingInterface
{
    /**
     * @inheritDoc
     */
    public function describe(ResultTypeRuleInterface $rule, array $missingRecommended): string
    {
        return $rule->getLeftOutText() === '' ? '' : (string) __($rule->getLeftOutText());
    }
}
