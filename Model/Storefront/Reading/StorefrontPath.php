<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Storefront\Reading;

use Hryvinskyi\RichSnippets\Api\StorefrontPathInterface;

/**
 * Checks and normalises a storefront path given by a person
 *
 * Bytes outside ASCII are percent-encoded, as a browser sends them. The store-picking parameters are the platform's
 * own (`___store`, `___from_store`), matched whatever their case.
 */
class StorefrontPath implements StorefrontPathInterface
{
    private const MAX_LENGTH = 2048;
    private const REFUSED_CHARACTERS = '~[\x00-\x20\x7F\\\\]~';
    private const NON_ASCII = '~[\x80-\xFF]~';
    private const STORE_PARAMETERS = ['___store', '___from_store'];

    /**
     * @param AddressParser $parser
     */
    public function __construct(private readonly AddressParser $parser)
    {
    }

    /**
     * @inheritDoc
     */
    public function normalize(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }
        if (!str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('A storefront path starts with "/".');
        }
        if (preg_match(self::REFUSED_CHARACTERS, $path) === 1) {
            throw new \InvalidArgumentException('A storefront path has no spaces, control characters or backslashes.');
        }
        $path = explode('#', $path, 2)[0];
        $path = '/' . ltrim($path, '/');
        $path = (string) preg_replace_callback(
            self::NON_ASCII,
            static fn (array $byte): string => rawurlencode($byte[0]),
            $path
        );
        if (strlen($path) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('A storefront path is at most 2048 characters long.');
        }
        if ($this->picksStore($path)) {
            throw new \InvalidArgumentException('A storefront path does not pick a store view; choose it instead.');
        }

        return $path;
    }

    /**
     * Whether the path's query has a parameter that picks a store view
     *
     * @param string $path
     * @return bool
     */
    private function picksStore(string $path): bool
    {
        $query = explode('?', $path, 2)[1] ?? '';
        foreach (explode('&', $query) as $pair) {
            $name = strtolower($this->parser->decode(explode('=', $pair, 2)[0]));
            $name = (string) preg_replace('~\[.*\z~', '', $name);
            if (in_array($name, self::STORE_PARAMETERS, true)) {
                return true;
            }
        }

        return false;
    }
}
