<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Variant;

use Hryvinskyi\RichSnippets\Model\Variant\VariantAddress;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\TestCase;

/**
 * A variant's address names one option per configurable attribute, and the product page reads it back
 */
class VariantAddressTest extends TestCase
{
    public function testAVariantsAddressIsTheProductsAddressWithItsOptions(): void
    {
        $address = new VariantAddress();

        self::assertSame(
            'https://shop.example/weekender.html?color=49&size=167',
            $address->url('https://shop.example/weekender.html', ['color' => 49, 'size' => 167])
        );
        self::assertSame(
            'https://shop.example/catalog/product/view/id/5?a=1&color=49',
            $address->url('https://shop.example/catalog/product/view/id/5?a=1', ['color' => 49])
        );
    }

    public function testARequestNamesTheOptionsOnlyWhenItNamesAValidOneForEveryAttribute(): void
    {
        $address = new VariantAddress();

        self::assertSame(
            ['color' => 49, 'size' => 167],
            $address->requested($this->request(['color' => '49', 'size' => '167']), ['color', 'size'])
        );
        self::assertNull(
            $address->requested($this->request(['color' => '49']), ['color', 'size']),
            'One attribute missing.'
        );
        self::assertNull($address->requested($this->request(['color' => 'tan', 'size' => '167']), ['color', 'size']));
        self::assertNull($address->requested($this->request([]), []));
    }

    /**
     * A request with parameters
     *
     * @param array<string, string> $params
     * @return RequestInterface
     */
    private function request(array $params): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $name): ?string => $params[$name] ?? null);

        return $request;
    }
}
