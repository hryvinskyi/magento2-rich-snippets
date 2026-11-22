<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration;

use Magento\Framework\Module\ModuleListInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The module and every module it follows are enabled in the installation the container is built from
 */
class ModuleRegistrationTest extends TestCase
{
    public function testTheModuleIsEnabled(): void
    {
        self::assertTrue(
            $this->moduleList()->has('Hryvinskyi_RichSnippets'),
            'Hryvinskyi_RichSnippets is missing from the enabled modules.'
        );
    }

    #[DataProvider('followedModules')]
    public function testEveryModuleItFollowsIsEnabled(string $module): void
    {
        self::assertTrue($this->moduleList()->has($module), $module . ' is not enabled.');
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function followedModules(): array
    {
        return [
            ['Magento_Store'],
            ['Magento_Directory'],
            ['Magento_Config'],
            ['Magento_Backend'],
            ['Magento_Theme'],
            ['Magento_Cms'],
            ['Magento_Catalog'],
            ['Magento_CatalogInventory'],
            ['Magento_ConfigurableProduct'],
            ['Magento_UrlRewrite'],
            ['Magento_CatalogUrlRewrite'],
            ['Magento_Review'],
            ['Magento_Tax'],
            ['Magento_Cron'],
            ['Magento_Email'],
            ['Magento_PageCache'],
            ['Magento_ImportExport'],
            ['Magento_CatalogImportExport'],
            ['Hryvinskyi_Base'],
            ['Hryvinskyi_HeadTagManager'],
            ['Hryvinskyi_AdminUi'],
            ['Hryvinskyi_AdminSetupWizard'],
            ['Hryvinskyi_JsonTemplate'],
        ];
    }

    /**
     * @return ModuleListInterface
     */
    private function moduleList(): ModuleListInterface
    {
        $moduleList = Bootstrap::getObjectManager()->get(ModuleListInterface::class);
        if (!$moduleList instanceof ModuleListInterface) {
            self::fail('The container does not resolve the module list.');
        }

        return $moduleList;
    }
}
