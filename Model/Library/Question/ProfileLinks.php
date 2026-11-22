<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library\Question;

/**
 * Finds the links to profiles on well-known hosts in a piece of HTML
 *
 * A link counts when it is an absolute `http` or `https` address in an `href` and its host is one of the hosts or a
 * subdomain of one (`www.`, a country subdomain). An escaped ampersand in an address reads as `&`. Each address is
 * listed once, in the order of the HTML.
 */
class ProfileLinks
{
    private const HREF_PATTERN = '/\bhref\s*=\s*(["\'])(.*?)\1/is';
    private const HOST_PATTERN = '#\Ahttps?://(?:[^/?\#@]*@)?([^/?\#:]+)#i';
    private const ENTITIES = ['&amp;' => '&', '&#38;' => '&'];

    /**
     * The profile links of the HTML
     *
     * @param string $html
     * @param list<string> $hosts the hosts that hold profiles, in lower case (`instagram.com`)
     * @return list<string>
     */
    public function links(string $html, array $hosts): array
    {
        if (preg_match_all(self::HREF_PATTERN, $html, $matches) === false) {
            return [];
        }
        $links = [];
        foreach ($matches[2] as $href) {
            $url = trim(strtr($href, self::ENTITIES));
            if ($this->onHost($url, $hosts)) {
                $links[$url] = true;
            }
        }

        return array_map('strval', array_keys($links));
    }

    /**
     * Whether an address is an absolute web address on one of the hosts
     *
     * @param string $url
     * @param list<string> $hosts
     * @return bool
     */
    private function onHost(string $url, array $hosts): bool
    {
        if (preg_match(self::HOST_PATTERN, $url, $match) !== 1) {
            return false;
        }
        $host = strtolower($match[1]);
        foreach ($hosts as $known) {
            if ($host === $known || str_ends_with($host, '.' . $known)) {
                return true;
            }
        }

        return false;
    }
}
