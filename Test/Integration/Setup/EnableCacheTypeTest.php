<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Setup;

use Hryvinskyi\RichSnippets\Model\Cache\Type\RichSnippetsCache;
use Hryvinskyi\RichSnippets\Setup\Patch\Data\EnableCacheType;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\DataObject;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The module's cache type is listed in Cache Management, and the data patch turns it on where it is disabled
 */
class EnableCacheTypeTest extends TestCase
{
    /**
     * @var bool
     */
    private bool $wasEnabled = false;

    /**
     * Remember whether the type was on, so the test leaves the deployment configuration as it found it
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->wasEnabled = $this->freshState()->isEnabled(RichSnippetsCache::TYPE_IDENTIFIER);
    }

    /**
     * Put the cache type back to the status it had before the test
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cacheManager()->setEnabled([RichSnippetsCache::TYPE_IDENTIFIER], $this->wasEnabled);
    }

    public function testTheTypeIsListedInCacheManagement(): void
    {
        $typeList = Bootstrap::getObjectManager()->get(TypeListInterface::class);
        self::assertInstanceOf(TypeListInterface::class, $typeList);

        $type = $typeList->getTypes()[RichSnippetsCache::TYPE_IDENTIFIER] ?? null;

        self::assertInstanceOf(DataObject::class, $type);
        self::assertSame('Rich Snippets', $this->text($type->getData('cache_type')));
        self::assertSame(
            'What the module derives from the stored snippets',
            $this->text($type->getData('description'))
        );
        self::assertSame(RichSnippetsCache::CACHE_TAG, $type->getData('tags'));
    }

    public function testApplyingThePatchEnablesADisabledType(): void
    {
        $this->cacheManager()->setEnabled([RichSnippetsCache::TYPE_IDENTIFIER], false);
        self::assertFalse($this->freshState()->isEnabled(RichSnippetsCache::TYPE_IDENTIFIER));

        $patch = Bootstrap::getObjectManager()->get(EnableCacheType::class);
        self::assertInstanceOf(EnableCacheType::class, $patch);

        self::assertSame($patch, $patch->apply());
        self::assertTrue($this->freshState()->isEnabled(RichSnippetsCache::TYPE_IDENTIFIER));
        self::assertSame([], $patch::getDependencies());
        self::assertSame([], $patch->getAliases());
    }

    /**
     * A listed value as text
     *
     * @param mixed $value
     * @return string
     */
    private function text(mixed $value): string
    {
        self::assertTrue(is_string($value) || $value instanceof \Stringable);

        return (string) $value;
    }

    /**
     * A state read from the deployment configuration anew
     *
     * @return StateInterface
     */
    private function freshState(): StateInterface
    {
        $state = Bootstrap::getObjectManager()->create(StateInterface::class);
        self::assertInstanceOf(StateInterface::class, $state);

        return $state;
    }

    /**
     * The shared cache manager, which persists the status to the deployment configuration
     *
     * @return Manager
     */
    private function cacheManager(): Manager
    {
        $manager = Bootstrap::getObjectManager()->get(Manager::class);
        self::assertInstanceOf(Manager::class, $manager);

        return $manager;
    }
}
