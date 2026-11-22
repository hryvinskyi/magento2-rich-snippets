<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Admin\Check;

use Hryvinskyi\RichSnippets\Model\Admin\Check\ScheduleLine;
use Hryvinskyi\RichSnippets\Model\Config\CheckSettings;
use Hryvinskyi\RichSnippets\Model\Config\RecipientList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * The last line of "How the check works": the start time of the settings while the nightly check is on, their
 * default when none is stored, and that it runs only on request while it is off
 */
class ScheduleLineTest extends TestCase
{
    public function testSwitchedOnItNamesTheStartTimeOfTheSettings(): void
    {
        self::assertSame(
            'It runs every night at 22:30 and after an import of products. The time is set in Settings.',
            $this->line(true, ' 22:30 ')
        );
    }

    public function testSwitchedOnWithNoTimeStoredItNamesTheDefault(): void
    {
        self::assertStringContainsString('every night at 03:00', $this->line(true, null));
    }

    public function testSwitchedOffItSaysTheCheckRunsOnlyWhenAskedFor(): void
    {
        self::assertSame(
            'It runs only when someone presses Check now: the nightly check is switched off in Settings.',
            $this->line(false, '22:30')
        );
    }

    /**
     * @param bool $enabled
     * @param string|null $time
     * @return string
     */
    private function line(bool $enabled, ?string $time): string
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => $path === 'hryvinskyi_rich_snippets/check/enabled' && $enabled
        );
        $config->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $path === 'hryvinskyi_rich_snippets/check/time' ? $time : null
        );

        return (new ScheduleLine(new CheckSettings($config, new RecipientList())))->line();
    }
}
