<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\Data\FieldInterface;
use Hryvinskyi\RichSnippets\Api\FieldCatalogInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Check\RunTimes;
use Hryvinskyi\RichSnippets\Model\Check\FindingWording;
use Hryvinskyi\RichSnippets\Model\Check\Tone\BadToneWording;
use Hryvinskyi\RichSnippets\Model\Check\Tone\NoteToneWording;
use Hryvinskyi\RichSnippets\Model\Check\Tone\WarnToneWording;
use Hryvinskyi\RichSnippets\Model\Wording\CountWords;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Builds the wording of the catalog check over the module's rules, a field catalog of a few labelled fields and a
 * time zone of UTC
 */
trait BuildsCheckWording
{
    /**
     * The finding wording with the tones the container configures, the fields labelled as given
     *
     * @param array<string, string> $labels the label of each field path
     * @return FindingWording
     */
    private function findingWording(array $labels): FindingWording
    {
        $fields = $this->createMock(FieldCatalogInterface::class);
        $fields->method('getField')->willReturnCallback(function (string $path) use ($labels): ?FieldInterface {
            if (!isset($labels[$path])) {
                return null;
            }
            $field = $this->createMock(FieldInterface::class);
            $field->method('getLabel')->willReturn($labels[$path]);

            return $field;
        });

        $plurals = new EnglishPlurals();

        return new FindingWording(ModuleRules::pool(), $fields, new CountWords($plurals), $plurals, [
            'bad' => new BadToneWording(),
            'warn' => new WarnToneWording(),
            'note' => new NoteToneWording(),
        ]);
    }

    /**
     * The times of runs told in UTC, the clock's day being today
     *
     * @param FixedClock $clock
     * @return RunTimes
     */
    private function runTimes(FixedClock $clock): RunTimes
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('date')->willReturnCallback(
            static fn (mixed $seconds): \DateTime => (new \DateTime('@' . (is_int($seconds) ? $seconds : 0)))
                ->setTimezone(new \DateTimeZone('UTC'))
        );
        $timezone->method('formatDate')->willReturnCallback(
            static fn (mixed $date): string => $date instanceof \DateTimeInterface ? $date->format('j M Y') : ''
        );

        return new RunTimes($timezone, $clock, new EnglishPlurals());
    }
}
