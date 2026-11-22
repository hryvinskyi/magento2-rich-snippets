<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Tone;

use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;

/**
 * Words why a finding of one tone matters and what to do about it, in the admin's language
 */
interface ToneWordingInterface
{
    /**
     * Why the gap matters
     *
     * @param FindingInterface $finding
     * @param ResultTypeRuleInterface|null $rule the finding's kind of result; null when no rule has its code
     * @param string $phrase the kind of result inside a sentence ("a merchant listing"), translated
     * @return string
     */
    public function description(FindingInterface $finding, ?ResultTypeRuleInterface $rule, string $phrase): string;

    /**
     * What to do about it
     *
     * @param FindingInterface $finding
     * @param string|null $field the label of the field the template read for the property; null when none is known
     * @return string
     */
    public function fix(FindingInterface $finding, ?string $field): string;
}
