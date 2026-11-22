<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config;

/**
 * Reads the e-mail addresses the findings of the catalog check go to
 *
 * Addresses are separated by commas; whitespace around each is dropped. An address is something before an `@`,
 * something after it, a dot and something after that, none of which holds whitespace, a comma or another `@`. No
 * addresses at all is valid: the findings are then sent to nobody.
 */
class RecipientList
{
    private const ADDRESS = '/\A[^\s@,]+@[^\s@,]+\.[^\s@,]+\z/';
    private const SEPARATOR = ',';

    /**
     * The addresses of a list, in its order; null when an entry is not an address
     *
     * @param string $list
     * @return list<string>|null
     */
    public function addresses(string $list): ?array
    {
        if (trim($list) === '') {
            return [];
        }
        $addresses = [];
        foreach (explode(self::SEPARATOR, $list) as $entry) {
            $address = trim($entry);
            if (preg_match(self::ADDRESS, $address) !== 1) {
                return null;
            }
            $addresses[] = $address;
        }

        return $addresses;
    }
}
