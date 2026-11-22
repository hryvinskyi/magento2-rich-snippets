<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Check;

use Hryvinskyi\RichSnippets\Model\Config\CheckSettings;

/**
 * Says when the catalog check runs by itself, as the Test page's "How the check works" panel ends with it
 *
 * Switched on, it runs every night at the start time of the settings and after an import of products; switched off,
 * only when someone asks for it. A start time that is not stored reads as the settings' default.
 */
class ScheduleLine
{
    /** The start time the settings ship with */
    private const DEFAULT_TIME = '03:00';

    /**
     * @param CheckSettings $settings
     */
    public function __construct(private readonly CheckSettings $settings)
    {
    }

    /**
     * The sentence, translated
     *
     * @return string
     */
    public function line(): string
    {
        if (!$this->settings->isEnabled()) {
            return (string) __(
                'It runs only when someone presses Check now: the nightly check is switched off in Settings.'
            );
        }
        $time = $this->settings->startAt();

        return (string) __(
            'It runs every night at %1 and after an import of products. The time is set in Settings.',
            $time === '' ? self::DEFAULT_TIME : $time
        );
    }
}
