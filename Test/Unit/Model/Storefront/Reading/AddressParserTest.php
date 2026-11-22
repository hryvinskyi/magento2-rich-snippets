<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Model\Storefront\Reading;

use Hryvinskyi\RichSnippets\Model\Storefront\Reading\AddressParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Addresses are split the way RFC 3986 reads them, so a user name or a broken port is never taken for the host
 */
class AddressParserTest extends TestCase
{
    /**
     * @return array<string, array{string, array{scheme: string|null, user: string|null, host: string|null,
     *     port: int|null, path: string, query: string|null}}>
     */
    public static function addresses(): array
    {
        $none = ['scheme' => null, 'user' => null, 'host' => null, 'port' => null, 'path' => '', 'query' => null];

        return [
            'a full address' => [
                'HTTPS://Shop.Test:8443/bags/satchel.html?x=1#reviews',
                ['scheme' => 'https', 'host' => 'shop.test', 'port' => 8443, 'path' => '/bags/satchel.html',
                    'query' => 'x=1'] + $none,
            ],
            'a user name before the host' => [
                'http://shop.test@evil.example/',
                ['scheme' => 'http', 'user' => 'shop.test', 'host' => 'evil.example', 'path' => '/'] + $none,
            ],
            'a port that is not a number' => ['http://evil.example:abc/', ['scheme' => 'http', 'path' => '/'] + $none],
            'two user names' => ['http://a@b@evil.example/', ['scheme' => 'http', 'path' => '/'] + $none],
            'a host with no scheme' => ['//evil.example/x', ['host' => 'evil.example', 'path' => '/x'] + $none],
            'a path' => ['/checkout/cart?step=2', ['path' => '/checkout/cart', 'query' => 'step=2'] + $none],
            'a script address' => ['javascript:alert(1)', ['scheme' => 'javascript', 'path' => 'alert(1)'] + $none],
        ];
    }

    /**
     * @param string $address
     * @param array{scheme: string|null, user: string|null, host: string|null, port: int|null, path: string,
     *     query: string|null} $parts
     */
    #[DataProvider('addresses')]
    public function testAnAddressIsSplitIntoItsParts(string $address, array $parts): void
    {
        $parsed = (new AddressParser())->parse($address);
        ksort($parsed);
        ksort($parts);

        self::assertSame($parts, $parsed);
    }

    public function testPercentEncodedTextIsDecodedAndAPlusStaysAPlus(): void
    {
        self::assertSame(
            '___store größe a+b %zz',
            (new AddressParser())->decode('%5F%5f_store gr%C3%B6%C3%9Fe a+b %zz')
        );
    }
}
