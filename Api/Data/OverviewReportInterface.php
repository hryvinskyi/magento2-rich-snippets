<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What the Overview says about the shop's snippets in a store view, or over every store view
 *
 * The counts of pages come from the last finished catalog check, in the scope; everything else is read as it stands.
 *
 * @api
 */
interface OverviewReportInterface
{
    /**
     * The store view; null for every store view
     *
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * How far the shop has set up its snippets; none only when the shop has no page snippet at all
     *
     * @return SetupState
     */
    public function getSetupState(): SetupState;

    /**
     * The page snippets output in the scope, switched on or off
     *
     * @return int
     */
    public function getSnippetsAdded(): int;

    /**
     * The switched-on page snippets output in the scope
     *
     * @return int
     */
    public function getSnippetsOn(): int;

    /**
     * The kinds of page the switched-on page snippets are set for, in the kinds' order
     *
     * @return list<string>
     */
    public function getKindsOn(): array;

    /**
     * The shop's store views the switched-on page snippets are output in, within the scope, in ascending order
     *
     * @return list<int>
     */
    public function getStoreIdsOn(): array;

    /**
     * When the first of the page snippets output in the scope was added, in Unix seconds; null when there is none
     *
     * @return int|null
     */
    public function getFirstAddedAt(): ?int;

    /**
     * The last catalog check that checked every page; null before one has
     *
     * @return CheckRunInterface|null
     */
    public function getLastRun(): ?CheckRunInterface;

    /**
     * The pages the last check found carrying at least one snippet
     *
     * None while nothing is live; null before a check has finished.
     *
     * @return int|null
     */
    public function getPagesWithSnippets(): ?int;

    /**
     * The pages the last check found invalid, by kind of page; none while nothing is live
     *
     * @return array<string,int>
     */
    public function getInvalidPagesByKind(): array;

    /**
     * How the script blocks are laid out; null when the store views in the scope differ
     *
     * @return OutputFormat|null
     */
    public function getOutputFormat(): ?OutputFormat;

    /**
     * Where the script blocks are written: the position setting's value; null when the store views in the scope differ
     *
     * @return string|null
     */
    public function getOutputPosition(): ?string;

    /**
     * Whether the theme's own Product markup is still on the product pages
     *
     * Still there when it is in any store view of the scope.
     *
     * @return ThemeMarkupState
     */
    public function getThemeMarkup(): ThemeMarkupState;

    /**
     * Every kind of page, in the kinds' order
     *
     * @return list<KindOverviewInterface>
     */
    public function getKinds(): array;

    /**
     * The kinds of Google result the last check counted blocks for, in the rules' order; none while nothing is live
     *
     * @return list<FeatureOverviewInterface>
     */
    public function getFeatures(): array;

    /**
     * What wants fixing, each with the screen that fixes it, in the providers' order
     *
     * @return list<AttentionItemInterface>
     */
    public function getAttentionItems(): array;
}
