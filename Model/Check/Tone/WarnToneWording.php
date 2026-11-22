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
 * A recommended property left out: worth filling where there is a value, harmless where there is none
 */
class WarnToneWording implements ToneWordingInterface
{
    /**
     * @inheritDoc
     */
    public function description(FindingInterface $finding, ?ResultTypeRuleInterface $rule, string $phrase): string
    {
        return (string) __('%1 is left out. Google recommends it for %2.', $finding->getPath(), $phrase);
    }

    /**
     * @inheritDoc
     */
    public function fix(FindingInterface $finding, ?string $field): string
    {
        return $field === null
            ? (string) __(
                'Fill what the snippet reads for %1 where there is a value, or leave it out: nothing breaks.',
                $finding->getPath()
            )
            : (string) __(
                'Fill %1 where there is a value. Where there is none, the property is simply left out.',
                $field
            );
    }
}
