<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration;

use Magento\Backend\Model\Menu\Config as MenuConfig;
use Magento\Framework\Acl\AclResource\ProviderInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The module's permissions sit where a role editor finds them, and its menu entry opens the module under
 * Marketing › SEO & Search for whoever may see it
 */
class AdminAccessTest extends TestCase
{
    public function testThePermissionsAreUnderSeoAndSearchAndTheSettingsUnderConfiguration(): void
    {
        $provider = Bootstrap::getObjectManager()->get(ProviderInterface::class);
        self::assertInstanceOf(ProviderInterface::class, $provider);
        $tree = $provider->getAclResources();

        $view = $this->find($tree, ['Magento_Backend::admin', 'Magento_Backend::marketing',
            'Magento_Backend::marketing_seo', 'Hryvinskyi_RichSnippets::view']);
        self::assertSame('See the module', $view['title'] ?? null);
        self::assertSame(
            ['Hryvinskyi_RichSnippets::snippets', 'Hryvinskyi_RichSnippets::test'],
            array_map(static fn (array $child): mixed => $child['id'] ?? null, $this->children($view))
        );

        $config = $this->find($tree, ['Magento_Backend::admin', 'Magento_Backend::stores',
            'Magento_Backend::stores_settings', 'Magento_Config::config', 'Hryvinskyi_RichSnippets::config']);
        self::assertSame('Change the settings', $config['title'] ?? null);
    }

    #[AppArea('adminhtml')]
    public function testTheMenuEntryOpensTheOverviewForWhoeverMaySeeTheModule(): void
    {
        $menuConfig = Bootstrap::getObjectManager()->get(MenuConfig::class);
        self::assertInstanceOf(MenuConfig::class, $menuConfig);
        $menu = $menuConfig->getMenu();

        $item = $menu->get('Hryvinskyi_RichSnippets::rich_snippets');
        self::assertNotNull($item);
        self::assertSame('Hryvinskyi_RichSnippets::view', $item->toArray()['resource'] ?? null);
        self::assertSame('Rich Snippets', (string) $item->getTitle());
        self::assertStringContainsString('richsnippets/overview/index', $item->getUrl());
        $parent = $menu->getParentItems('Hryvinskyi_RichSnippets::rich_snippets');
        self::assertSame('Magento_Backend::marketing_seo', end($parent) ? end($parent)->getId() : null);
    }

    /**
     * The resource at the end of a path of ids
     *
     * @param array<mixed> $tree
     * @param list<string> $path
     * @return array<mixed>
     */
    private function find(array $tree, array $path): array
    {
        $level = $tree;
        $found = [];
        foreach ($path as $id) {
            $found = [];
            foreach ($level as $resource) {
                if (is_array($resource) && ($resource['id'] ?? null) === $id) {
                    $found = $resource;
                    break;
                }
            }
            self::assertNotSame([], $found, $id . ' is not in the permission tree.');
            $level = $this->children($found);
        }

        return $found;
    }

    /**
     * The children of a resource
     *
     * @param array<mixed> $resource
     * @return list<array<mixed>>
     */
    private function children(array $resource): array
    {
        $children = [];
        foreach (is_array($resource['children'] ?? null) ? $resource['children'] : [] as $child) {
            if (is_array($child)) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
