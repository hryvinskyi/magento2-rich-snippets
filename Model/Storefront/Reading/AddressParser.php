<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Reading;

/**
 * Splits an address into its parts the way RFC 3986 reads it, and decodes percent-encoded text
 *
 * The scheme and the host are given in lower case. A part the address does not have is null; the path is always
 * there, empty when the address has none. An authority whose port is not a number has no host, so it can never be
 * taken for one.
 *
 * @phpstan-type Parts array{scheme: string|null, user: string|null, host: string|null, port: int|null,
 *     path: string, query: string|null}
 */
class AddressParser
{
    private const ADDRESS = '~\A(?:([a-zA-Z][a-zA-Z0-9+.-]*):)?(?://([^/?#]*))?([^?#]*)(?:\?([^#]*))?(?:#.*)?\z~s';
    private const AUTHORITY = '~\A(?:([^@]*)@)?(\[[^\]]*\]|[^:@]*)(?::(\d*))?\z~';
    private const ENCODED = '~%([0-9a-fA-F]{2})~';

    /**
     * The parts of an address
     *
     * @param string $address
     * @return Parts
     */
    public function parse(string $address): array
    {
        $parts = ['scheme' => null, 'user' => null, 'host' => null, 'port' => null, 'path' => '', 'query' => null];
        if (preg_match(self::ADDRESS, $address, $found, PREG_UNMATCHED_AS_NULL) !== 1) {
            return $parts;
        }
        $parts['scheme'] = $found[1] === null ? null : strtolower($found[1]);
        $parts['path'] = $found[3];
        $parts['query'] = $found[4];
        $authority = $found[2];
        if ($authority !== null && preg_match(self::AUTHORITY, $authority, $split, PREG_UNMATCHED_AS_NULL) === 1) {
            $parts['user'] = $split[1];
            $parts['host'] = $split[2] === '' ? null : strtolower($split[2]);
            $parts['port'] = ($split[3] ?? '') === '' ? null : (int) $split[3];
        }

        return $parts;
    }

    /**
     * Percent-encoded text decoded; a plus sign stays a plus sign
     *
     * @param string $text
     * @return string
     */
    public function decode(string $text): string
    {
        return (string) preg_replace_callback(
            self::ENCODED,
            static fn (array $byte): string => (string) hex2bin($byte[1]),
            $text
        );
    }
}
