<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;

/**
 * Creates the record of the library template a snippet was added from
 *
 * @api
 */
interface OriginFactoryInterface
{
    /**
     * An origin
     *
     * @param string $code the library template's code
     * @param int $version the library template's version, from 1
     * @return OriginInterface
     * @throws SnippetRuleException when the code is outside the code grammar or the version is below 1
     */
    public function create(string $code, int $version): OriginInterface;
}
