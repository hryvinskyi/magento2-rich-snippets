<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Check;

use Hryvinskyi\AdminUi\Api\Page\PluralPhrasesInterface;
use Hryvinskyi\RichSnippets\Model\Clock\ClockInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Says when a check ran and how long it took, in the admin's time zone, on the 24-hour clock
 *
 * A time of today reads "today at 03:00"; of another day, its date and time. As a value on its own, it reads
 * "Today, 03:00" or "21 Nov 2026, 03:00". A duration under two minutes is in seconds, under two hours in minutes, else
 * in hours, each in the plural form the admin's language gives the number.
 */
class RunTimes
{
    private const CLOCK = 'H:i';
    private const DAY = 'Y-m-d';
    private const MINUTE = 60;
    private const HOUR = 3600;

    /**
     * @param TimezoneInterface $timezone
     * @param ClockInterface $clock
     * @param PluralPhrasesInterface $plurals
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly ClockInterface $clock,
        private readonly PluralPhrasesInterface $plurals
    ) {
    }

    /**
     * When something happened, to follow a word ("today at 03:00", "21 Nov 2026 at 03:00")
     *
     * @param int $seconds Unix seconds
     * @param bool $capital whether it starts a sentence
     * @return string
     */
    public function at(int $seconds, bool $capital = false): string
    {
        $time = $this->timezone->date($seconds);
        $clock = $time->format(self::CLOCK);
        if ($time->format(self::DAY) === $this->timezone->date($this->clock->now())->format(self::DAY)) {
            return $capital ? (string) __('Today at %1', $clock) : (string) __('today at %1', $clock);
        }

        return (string) __('%1 at %2', $this->timezone->formatDate($time, \IntlDateFormatter::MEDIUM), $clock);
    }

    /**
     * When something happened, as a value standing on its own ("Today, 03:00", "21 Nov 2026, 03:00")
     *
     * @param int $seconds Unix seconds
     * @return string
     */
    public function moment(int $seconds): string
    {
        $time = $this->timezone->date($seconds);
        $clock = $time->format(self::CLOCK);
        if ($time->format(self::DAY) === $this->timezone->date($this->clock->now())->format(self::DAY)) {
            return (string) __('Today, %1', $clock);
        }

        return (string) __('%1, %2', $this->timezone->formatDate($time, \IntlDateFormatter::MEDIUM), $clock);
    }

    /**
     * The time of day something happened ("13:58")
     *
     * @param int $seconds Unix seconds
     * @return string
     */
    public function clock(int $seconds): string
    {
        return $this->timezone->date($seconds)->format(self::CLOCK);
    }

    /**
     * How long something took ("41 seconds", "3 minutes")
     *
     * @param int $seconds
     * @return string
     */
    public function duration(int $seconds): string
    {
        if ($seconds < 2 * self::MINUTE) {
            return $this->plurals->sentence($seconds, __('%1 second', $seconds), __('%1 seconds', $seconds));
        }
        if ($seconds < 2 * self::HOUR) {
            $minutes = (int) round($seconds / self::MINUTE);

            return $this->plurals->sentence($minutes, __('%1 minute', $minutes), __('%1 minutes', $minutes));
        }
        $hours = round($seconds / self::HOUR, 1);

        return $this->plurals->sentence($hours, __('%1 hour', $hours), __('%1 hours', $hours));
    }
}
