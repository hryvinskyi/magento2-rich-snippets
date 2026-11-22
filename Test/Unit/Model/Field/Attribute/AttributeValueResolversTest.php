<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Field\Attribute;

use Hryvinskyi\RichSnippets\Model\Field\Attribute\AttributeValueResolverPool;
use Hryvinskyi\RichSnippets\Model\Field\Attribute\BooleanValue;
use Hryvinskyi\RichSnippets\Model\Field\Attribute\DateTimeValue;
use Hryvinskyi\RichSnippets\Model\Field\Attribute\DateValue;
use Hryvinskyi\RichSnippets\Model\Field\Attribute\MediaImageValue;
use Hryvinskyi\RichSnippets\Model\Field\Attribute\MultiselectValue;
use Hryvinskyi\RichSnippets\Model\Field\Attribute\SelectValue;
use Hryvinskyi\RichSnippets\Model\Field\Attribute\TextValue;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Each input type's stored value becomes the value its field promises a template
 */
class AttributeValueResolversTest extends TestCase
{
    private const LABELS = [49 => 'Tan', 50 => 'Olive', 51 => 'Black'];

    public function testASelectYieldsItsOptionsLabel(): void
    {
        $select = new SelectValue();

        self::assertSame('Tan', $select->resolve('49', self::LABELS, 1));
        self::assertSame('Olive', $select->resolve(50, self::LABELS, 1));
        self::assertNull($select->resolve('999', self::LABELS, 1), 'An option without a label is nothing.');
        self::assertNull($select->resolve(null, self::LABELS, 1));
    }

    public function testAMultiselectYieldsTheListOfItsOptionsLabels(): void
    {
        $multiselect = new MultiselectValue();

        self::assertSame(['Tan', 'Black'], $multiselect->resolve('49,51', self::LABELS, 1));
        self::assertSame(['Olive'], $multiselect->resolve('50,999', self::LABELS, 1));
        self::assertNull($multiselect->resolve('', self::LABELS, 1));
    }

    public function testABooleanYieldsABoolAndNothingWhenNoneIsStored(): void
    {
        $boolean = new BooleanValue();

        self::assertTrue($boolean->resolve('1', [], 1));
        self::assertFalse($boolean->resolve(0, [], 1));
        self::assertNull($boolean->resolve(null, [], 1));
    }

    public function testADateYieldsIso8601(): void
    {
        $date = new DateValue();

        self::assertSame('2026-11-30', $date->resolve('2026-11-30 00:00:00', [], 1));
        self::assertNull($date->resolve('soon', [], 1));
        self::assertNull($date->resolve(null, [], 1));
    }

    public function testAMomentYieldsIso8601WithTheStoreViewsOffset(): void
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('scopeDate')->willReturnCallback(
            static fn (int $store, string $date): \DateTime => (new \DateTime($date, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('Europe/Berlin'))
        );

        self::assertSame(
            '2026-11-30T15:00:00+01:00',
            (new DateTimeValue($timezone))->resolve('2026-11-30 14:00:00', [], 1)
        );
    }

    public function testAnImageYieldsTheAbsoluteAddressOfItsFileAndNothingWhenNoneIsChosen(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('isFrontUrlSecure')->willReturn(true);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn (string $type): string => $type === UrlInterface::URL_TYPE_MEDIA
                ? 'https://shop.example/media/'
                : 'https://shop.example/'
        );
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $image = new MediaImageValue(new StoreAddresses($storeManager));

        self::assertSame(
            'https://shop.example/media/catalog/product/f/s/field-satchel-1.jpg',
            $image->resolve('/f/s/field-satchel-1.jpg', [], 1)
        );
        self::assertNull($image->resolve('no_selection', [], 1));
        self::assertNull($image->resolve('', [], 1));
    }

    public function testTextIsKeptAsItIsAndAnInputWithoutAResolverIsReadAsText(): void
    {
        $pool = new AttributeValueResolverPool(new TextValue(), ['select' => new SelectValue()]);

        self::assertSame('4062001000010', $pool->get('text')->resolve('4062001000010', [], 1));
        self::assertSame('12', $pool->get('weight')->resolve(12, [], 1));
        self::assertNull($pool->get('textarea')->resolve('', [], 1));
        self::assertInstanceOf(SelectValue::class, $pool->get('select'));
    }
}
