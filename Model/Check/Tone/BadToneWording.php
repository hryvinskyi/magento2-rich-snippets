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
 * A required property missing: the pages are output without it and reported as invalid, so each needs a value
 */
class BadToneWording implements ToneWordingInterface
{
    /**
     * @inheritDoc
     */
    public function description(FindingInterface $finding, ?ResultTypeRuleInterface $rule, string $phrase): string
    {
        return (string) __(
            'Google needs %1 for %2. These pages are output without it and are reported as invalid.',
            $finding->getPath(),
            $phrase
        );
    }

    /**
     * @inheritDoc
     */
    public function fix(FindingInterface $finding, ?string $field): string
    {
        return $field === null
            ? (string) __(
                'Give each of these pages a value for %1, or change what the snippet writes there.',
                $finding->getPath()
            )
            : (string) __('Fill %1 for each of these pages.', $field);
    }
}
