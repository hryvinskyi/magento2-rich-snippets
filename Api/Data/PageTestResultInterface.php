<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What one page test found: the page's JSON-LD blocks with their checks, the theme's own microdata, and the
 * snippets the page does not carry with the reason; or why the page could not be read
 *
 * A failed test has a failure and nothing found.
 *
 * @api
 */
interface PageTestResultInterface
{
    /**
     * The page tested: its kind, entity and store view
     *
     * @return PageEntityInterface
     */
    public function getPage(): PageEntityInterface;

    /**
     * The page's name in the store view; the path for a page given by its path
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The path asked for, from the store view's base address on
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * The path the page was read at after the redirects that were followed; null when it could not be read
     *
     * @return string|null
     */
    public function getReadPath(): ?string;

    /**
     * When the test ran, in Unix seconds
     *
     * @return int
     */
    public function getTestedAt(): int;

    /**
     * How long the test took, in milliseconds
     *
     * @return int
     */
    public function getDurationMs(): int;

    /**
     * Why the page could not be read; null when it was read
     *
     * @return ReadFailure|null
     */
    public function getFailure(): ?ReadFailure;

    /**
     * The HTTP status of a failed read's last answer; null when it was read or the storefront gave none
     *
     * @return int|null
     */
    public function getHttpStatus(): ?int;

    /**
     * The page's JSON-LD blocks, in page order
     *
     * @return list<TestedBlockInterface>
     */
    public function getBlocks(): array;

    /**
     * The theme's microdata items of the types a snippet replaces
     *
     * Items the page's HTML describes with microdata, which Rich Snippets does not write; an item of another type (an
     * Organization, a BreadcrumbList) is not listed.
     *
     * @return list<MicrodataItemInterface>
     */
    public function getThemeMarkup(): array;

    /**
     * The page snippets not set for this page, each with the reason
     *
     * @return list<SnippetExclusionInterface>
     */
    public function getExclusions(): array;

    /**
     * The snippets set for this page that the page as read does not carry, each with the reason
     *
     * @return list<UnwrittenSnippetInterface>
     */
    public function getUnwritten(): array;
}
