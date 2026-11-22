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
 * A gap that needs nothing: why it is shown at all — the rule's status, else why it leaves the result out, else that
 * the property is left out — and nothing to fix
 */
class NoteToneWording implements ToneWordingInterface
{
    /**
     * @inheritDoc
     */
    public function description(FindingInterface $finding, ?ResultTypeRuleInterface $rule, string $phrase): string
    {
        $text = $rule?->getStatusText() ?? '';
        if ($text === '') {
            $text = $rule?->getLeftOutText() ?? '';
        }

        return $text !== ''
            ? (string) __($text)
            : (string) __('%1 is left out. Nothing to fix.', $finding->getPath());
    }

    /**
     * @inheritDoc
     */
    public function fix(FindingInterface $finding, ?string $field): string
    {
        return (string) __('Nothing to fix.');
    }
}
