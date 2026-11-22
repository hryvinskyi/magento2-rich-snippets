<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Integration\Model\Engine;

use Hryvinskyi\JsonTemplate\Api\BaseUrlInterface;
use Hryvinskyi\JsonTemplate\Api\FilterPoolInterface;
use Hryvinskyi\JsonTemplate\Api\IncludeResolverInterface;
use Hryvinskyi\JsonTemplate\Api\ItemAliasResolverInterface;
use Hryvinskyi\JsonTemplate\Api\ScopeFactoryInterface;
use Hryvinskyi\JsonTemplate\Api\TemplateEngineInterface;
use Hryvinskyi\JsonTemplate\Api\TimezoneProviderInterface;
use Hryvinskyi\JsonTemplate\Api\VariableProviderPoolInterface;
use Hryvinskyi\JsonTemplate\Engine\Filter\Date\UtcTimezone;
use Hryvinskyi\JsonTemplate\Engine\Filter\Url\IdentityBaseUrl;
use Hryvinskyi\JsonTemplate\Engine\Inclusion\NoIncludes;
use Hryvinskyi\JsonTemplate\Engine\Scope\NoItemAliases;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageScopeFactoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The snippets evaluate through an engine of their own: the engine's filters with the store view's `url` and `date`,
 * and roots of their own, while the engine's global bindings stay neutral for every other template
 */
#[AppArea('frontend')]
class SnippetTemplateEngineTest extends TestCase
{
    private const ENGINE = 'HryvinskyiRichSnippetsTemplateEngine';
    private const FILTERS = 'HryvinskyiRichSnippetsFilterPool';
    private const STORE_FILTERS = ['url', 'date'];
    private const TEMPLATE = '{"url": "{{ path | url }}", "date": "{{ when | date }}"}';
    private const WHEN = '2026-11-30 10:15:00';
    private const SNIPPET_ROOTS = ['product', 'reviews', 'category', 'page', 'breadcrumbs', 'store', 'context'];

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testTheSnippetsPoolHoldsEveryFilterOfTheEnginesPoolAndReplacesOnlyUrlAndDate(): void
    {
        $engines = $this->get(FilterPoolInterface::class);
        $snippets = $this->objectManager->get(self::FILTERS);
        self::assertInstanceOf(FilterPoolInterface::class, $snippets);

        self::assertSame($engines->getNames(), $snippets->getNames());
        foreach ($engines->getNames() as $name) {
            if (in_array($name, self::STORE_FILTERS, true)) {
                self::assertNotSame($engines->get($name), $snippets->get($name), $name . ' is the store view\'s.');
                continue;
            }
            self::assertSame($engines->get($name), $snippets->get($name), $name . ' is the engine\'s own.');
        }
    }

    public function testTheSnippetsEngineWritesTheStoreViewsAddressAndTimeZone(): void
    {
        $store = $this->get(StoreManagerInterface::class)->getStore();
        self::assertInstanceOf(Store::class, $store);
        $base = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/') . '/';
        $zone = $this->get(TimezoneInterface::class)->getConfigTimezone(
            ScopeInterface::SCOPE_STORE,
            $store->getCode()
        );

        self::assertSame(
            [
                'url' => $base . 'bags/field-satchel.html',
                'date' => (new \DateTimeImmutable(self::WHEN, new \DateTimeZone($zone)))->format(DATE_ATOM),
            ],
            $this->evaluate($this->snippetsEngine())
        );
    }

    public function testTheEnginesGlobalBindingsStayNeutral(): void
    {
        self::assertInstanceOf(NoItemAliases::class, $this->get(ItemAliasResolverInterface::class));
        self::assertInstanceOf(NoIncludes::class, $this->get(IncludeResolverInterface::class));
        self::assertInstanceOf(IdentityBaseUrl::class, $this->get(BaseUrlInterface::class));
        self::assertInstanceOf(UtcTimezone::class, $this->get(TimezoneProviderInterface::class));
        self::assertSame(
            ['url' => 'bags/field-satchel.html', 'date' => '2026-11-30T10:15:00+00:00'],
            $this->evaluate($this->get(TemplateEngineInterface::class))
        );
    }

    public function testTheEnginesSharedPoolServesNoneOfTheSnippetsRoots(): void
    {
        $shared = $this->get(VariableProviderPoolInterface::class);
        $scope = $this->get(ScopeFactoryInterface::class)->create();

        foreach (self::SNIPPET_ROOTS as $root) {
            self::assertFalse($shared->has($root), $root . ' is not in the engine\'s shared pool.');
            self::assertFalse($scope->has($root), $root . ' is not known to a scope of the engine.');
        }
    }

    public function testASnippetScopeResolvesTheSnippetsRoots(): void
    {
        $storeId = (int) $this->get(StoreManagerInterface::class)->getStore()->getId();
        $page = $this->get(PageEntityFactoryInterface::class)->create('other', null, $storeId);
        $scope = $this->get(PageScopeFactoryInterface::class)->create($page);

        foreach (self::SNIPPET_ROOTS as $root) {
            self::assertTrue($scope->has($root), $root . ' is known to a snippet scope.');
        }
        self::assertSame(
            ['kind' => 'other', 'store' => $storeId],
            $this->snippetsEngine()->evaluate(
                '{"kind": "{{ context.kind }}", "store": "{{ context.store_id }}"}',
                $scope
            )
        );
    }

    /**
     * The address and date template evaluated by an engine
     *
     * @param TemplateEngineInterface $engine
     * @return mixed
     */
    private function evaluate(TemplateEngineInterface $engine): mixed
    {
        return $engine->evaluate(
            self::TEMPLATE,
            $this->get(ScopeFactoryInterface::class)->create(
                ['path' => 'bags/field-satchel.html', 'when' => self::WHEN]
            )
        );
    }

    /**
     * The snippets' template engine
     *
     * @return TemplateEngineInterface
     */
    private function snippetsEngine(): TemplateEngineInterface
    {
        $engine = $this->objectManager->get(self::ENGINE);
        self::assertInstanceOf(TemplateEngineInterface::class, $engine);

        return $engine;
    }

    /**
     * A service from the container
     *
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private function get(string $type): object
    {
        $service = $this->objectManager->get($type);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
