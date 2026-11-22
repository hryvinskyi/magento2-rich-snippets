<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;

/**
 * Every template of the library, as data modules ship (LibraryTemplateSourceInterface)
 *
 * Every definition is validated when the library is loaded, and its template compiled with the placeholders taken
 * out: one that is malformed fails the load. A template may use only building blocks of the library.
 *
 * @api
 */
interface LibraryTemplatePoolInterface
{
    /**
     * Every template, group by group in the order of the groups, then in the pool's order
     *
     * @return list<LibraryTemplateInterface>
     * @throws MalformedLibraryTemplateException when a definition is malformed
     */
    public function all(): array;

    /**
     * The template of a code
     *
     * @param string $code
     * @return LibraryTemplateInterface|null null when no template has the code
     * @throws MalformedLibraryTemplateException when a definition is malformed
     */
    public function get(string $code): ?LibraryTemplateInterface;

    /**
     * The library groups' labels by code, in the order the library lists them
     *
     * @return array<string,string>
     */
    public function groups(): array;
}
