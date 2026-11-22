<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Storefront\Reading;

use Hryvinskyi\RichSnippets\Model\Storefront\Reading\AddressParser;
use Hryvinskyi\RichSnippets\Model\Storefront\Reading\StorefrontPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A person gives only a path of the storefront: never a host, a scheme or a store-picking parameter
 */
class StorefrontPathTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function paths(): array
    {
        return [
            'empty is the home page' => ['', '/'],
            'the root' => ['/', '/'],
            'a page' => ['/checkout/cart', '/checkout/cart'],
            'a query is kept' => ['/catalogsearch/result/?q=bag', '/catalogsearch/result/?q=bag'],
            'a fragment is dropped' => ['/bags.html#reviews', '/bags.html'],
            'extra slashes at the start can never name a host' => ['//evil.example/x', '/evil.example/x'],
            'surrounding space is trimmed' => ['  /bags.html ', '/bags.html'],
            'non-ASCII is percent-encoded' => ['/größe.html', '/gr%C3%B6%C3%9Fe.html'],
            'a parameter that only looks like a store one' => ['/x?my___store=1', '/x?my___store=1'],
        ];
    }

    #[DataProvider('paths')]
    public function testAPathIsNormalised(string $given, string $expected): void
    {
        self::assertSame($expected, (new StorefrontPath(new AddressParser()))->normalize($given));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refused(): array
    {
        return [
            'a full address' => ['https://evil.example/'],
            'a scheme-relative address without the leading slash' => ['evil.example/x'],
            'a relative path' => ['checkout/cart'],
            'a space inside' => ['/check out'],
            'a control character' => ["/x\r\nHost: evil.example"],
            'a backslash' => ['/\\evil.example'],
            'the store parameter' => ['/bags.html?___store=de'],
            'the store parameter in another case' => ['/bags.html?a=1&___STORE=de'],
            'the from-store parameter, encoded' => ['/bags.html?%5F%5F%5Ffrom_store=en'],
            'too long' => ['/' . str_repeat('a', 2048)],
        ];
    }

    #[DataProvider('refused')]
    public function testWhatIsNotAStorefrontPathIsRefused(string $given): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new StorefrontPath(new AddressParser()))->normalize($given);
    }
}
