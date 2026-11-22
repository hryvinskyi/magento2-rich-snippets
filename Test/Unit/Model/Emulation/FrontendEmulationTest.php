<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Emulation;

use Hryvinskyi\RichSnippets\Model\Emulation\FrontendEmulation;
use Magento\Framework\App\Area;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Work runs as a guest of the store view's storefront in its default display currency, and everything is put back
 */
class FrontendEmulationTest extends TestCase
{
    public function testWorkRunsAsAGuestInTheDefaultCurrencyAndTheVisitorIsPutBack(): void
    {
        $context = new HttpContext([], [], new Json());
        $context->setValue('customer_group', 3, 0);
        $emulation = $this->createMock(Emulation::class);
        $emulation->expects(self::once())->method('startEnvironmentEmulation')->with(2, Area::AREA_FRONTEND, true);
        $emulation->expects(self::once())->method('stopEnvironmentEmulation');
        $runner = new FrontendEmulation($emulation, $context, $this->stores('EUR'));

        $seen = $runner->run(2, static fn (): array => [
            $context->getValue('customer_group'),
            $context->getValue('current_currency'),
        ]);

        self::assertSame([0, 'EUR'], $seen);
        self::assertSame(3, $context->getValue('customer_group'));
        $data = $context->toArray()['data'];
        self::assertIsArray($data);
        self::assertArrayNotHasKey('current_currency', $data);
    }

    public function testTheEmulationStopsWhenTheWorkFails(): void
    {
        $emulation = $this->createMock(Emulation::class);
        $emulation->expects(self::once())->method('stopEnvironmentEmulation');
        $runner = new FrontendEmulation($emulation, new HttpContext([], [], new Json()), $this->stores('USD'));

        $this->expectExceptionObject(new \RuntimeException('Failed.'));
        $runner->run(1, static function (): string {
            throw new \RuntimeException('Failed.');
        });
    }

    public function testARunInsideARunJoinsItForTheSameStoreViewAndIsRefusedForAnother(): void
    {
        $emulation = $this->createMock(Emulation::class);
        $emulation->expects(self::once())->method('startEnvironmentEmulation');
        $runner = new FrontendEmulation($emulation, new HttpContext([], [], new Json()), $this->stores('USD'));

        self::assertSame(
            'inner',
            $runner->run(1, static fn (): string => $runner->run(1, static fn (): string => 'inner'))
        );

        $other = new FrontendEmulation(
            $this->createMock(Emulation::class),
            new HttpContext([], [], new Json()),
            $this->stores('USD')
        );
        $this->expectException(\LogicException::class);
        $other->run(1, static fn (): string => $other->run(2, static fn (): string => 'other'));
    }

    /**
     * A store manager whose store views have a default display currency
     *
     * @param string $currency
     * @return StoreManagerInterface
     */
    private function stores(string $currency): StoreManagerInterface
    {
        $store = $this->createMock(Store::class);
        $store->method('getDefaultCurrencyCode')->willReturn($currency);
        $manager = $this->createMock(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);

        return $manager;
    }
}
