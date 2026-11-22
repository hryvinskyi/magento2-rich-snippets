<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config;

/**
 * Turns the time of the nightly catalog check into the cron expression that runs it every day
 *
 * The time is hours and minutes on the 24-hour clock, `HH:MM` with both digits of each. The expression is read in
 * the time zone the cron schedule uses, the default scope's, which is the admin's: no conversion is made.
 */
class CheckTimeExpression
{
    private const TIME = '/\A([01]\d|2[0-3]):([0-5]\d)\z/';

    /**
     * The cron expression of a time, `M H * * *`; null when the time is not `HH:MM`
     *
     * @param string $time
     * @return string|null
     */
    public function cronExpression(string $time): ?string
    {
        if (preg_match(self::TIME, $time, $parts) !== 1) {
            return null;
        }

        return (int) $parts[2] . ' ' . (int) $parts[1] . ' * * *';
    }
}
