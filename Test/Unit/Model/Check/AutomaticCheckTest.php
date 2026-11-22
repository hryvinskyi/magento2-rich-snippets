<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Check;

use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Model\Check\AutomaticCheck;
use Hryvinskyi\RichSnippets\Model\Config\CheckSettings;
use Hryvinskyi\RichSnippets\Model\Config\RecipientList;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * A check asked for by itself (the schedule, an import) is only asked for while the check is switched on
 */
class AutomaticCheckTest extends TestCase
{
    public function testSwitchedOffNothingIsAskedFor(): void
    {
        $catalogCheck = $this->createMock(CatalogCheckInterface::class);
        $catalogCheck->expects(self::never())->method('request');

        self::assertNull($this->automaticCheck(false, $catalogCheck)->request(CheckTrigger::Schedule));
    }

    public function testSwitchedOnTheCheckIsAskedForWithWhatAsked(): void
    {
        $catalogCheck = $this->createMock(CatalogCheckInterface::class);
        $catalogCheck->expects(self::once())->method('request')->with(CheckTrigger::Import);

        self::assertNotNull($this->automaticCheck(true, $catalogCheck)->request(CheckTrigger::Import));
    }

    /**
     * @param bool $enabled
     * @param CatalogCheckInterface $catalogCheck
     * @return AutomaticCheck
     */
    private function automaticCheck(bool $enabled, CatalogCheckInterface $catalogCheck): AutomaticCheck
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => $path === 'hryvinskyi_rich_snippets/check/enabled' && $enabled
        );

        return new AutomaticCheck(new CheckSettings($config, new RecipientList()), $catalogCheck);
    }
}
