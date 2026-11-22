<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\FeatureOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\KindOverviewInterface;
use Hryvinskyi\RichSnippets\Api\Data\OutputFormat;
use Hryvinskyi\RichSnippets\Api\Data\OverviewReportInterface;
use Hryvinskyi\RichSnippets\Api\Data\SetupState;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupState;

/**
 * What the Overview says about the shop's snippets in a store view, or over every store view, as it was read
 */
class OverviewReport implements OverviewReportInterface
{
    /**
     * @param int|null $storeId
     * @param SetupState $setupState
     * @param int $snippetsAdded
     * @param int $snippetsOn
     * @param list<string> $kindsOn
     * @param list<int> $storeIdsOn
     * @param int|null $firstAddedAt
     * @param CheckRunInterface|null $lastRun
     * @param int|null $pagesWithSnippets
     * @param array<string,int> $invalidPagesByKind
     * @param OutputFormat|null $outputFormat
     * @param string|null $outputPosition the setting value of the place
     * @param ThemeMarkupState $themeMarkup
     * @param list<KindOverviewInterface> $kinds
     * @param list<FeatureOverviewInterface> $features
     * @param list<AttentionItemInterface> $attentionItems
     */
    public function __construct(
        private readonly ?int $storeId,
        private readonly SetupState $setupState,
        private readonly int $snippetsAdded,
        private readonly int $snippetsOn,
        private readonly array $kindsOn,
        private readonly array $storeIdsOn,
        private readonly ?int $firstAddedAt,
        private readonly ?CheckRunInterface $lastRun,
        private readonly ?int $pagesWithSnippets,
        private readonly array $invalidPagesByKind,
        private readonly ?OutputFormat $outputFormat,
        private readonly ?string $outputPosition,
        private readonly ThemeMarkupState $themeMarkup,
        private readonly array $kinds,
        private readonly array $features,
        private readonly array $attentionItems
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): ?int
    {
        return $this->storeId;
    }

    /**
     * @inheritDoc
     */
    public function getSetupState(): SetupState
    {
        return $this->setupState;
    }

    /**
     * @inheritDoc
     */
    public function getSnippetsAdded(): int
    {
        return $this->snippetsAdded;
    }

    /**
     * @inheritDoc
     */
    public function getSnippetsOn(): int
    {
        return $this->snippetsOn;
    }

    /**
     * @inheritDoc
     */
    public function getKindsOn(): array
    {
        return $this->kindsOn;
    }

    /**
     * @inheritDoc
     */
    public function getStoreIdsOn(): array
    {
        return $this->storeIdsOn;
    }

    /**
     * @inheritDoc
     */
    public function getFirstAddedAt(): ?int
    {
        return $this->firstAddedAt;
    }

    /**
     * @inheritDoc
     */
    public function getLastRun(): ?CheckRunInterface
    {
        return $this->lastRun;
    }

    /**
     * @inheritDoc
     */
    public function getPagesWithSnippets(): ?int
    {
        return $this->pagesWithSnippets;
    }

    /**
     * @inheritDoc
     */
    public function getInvalidPagesByKind(): array
    {
        return $this->invalidPagesByKind;
    }

    /**
     * @inheritDoc
     */
    public function getOutputFormat(): ?OutputFormat
    {
        return $this->outputFormat;
    }

    /**
     * @inheritDoc
     */
    public function getOutputPosition(): ?string
    {
        return $this->outputPosition;
    }

    /**
     * @inheritDoc
     */
    public function getThemeMarkup(): ThemeMarkupState
    {
        return $this->themeMarkup;
    }

    /**
     * @inheritDoc
     */
    public function getKinds(): array
    {
        return $this->kinds;
    }

    /**
     * @inheritDoc
     */
    public function getFeatures(): array
    {
        return $this->features;
    }

    /**
     * @inheritDoc
     */
    public function getAttentionItems(): array
    {
        return $this->attentionItems;
    }
}
