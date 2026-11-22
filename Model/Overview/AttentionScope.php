<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview;

use Hryvinskyi\RichSnippets\Api\Data\AttentionScopeInterface;
use Hryvinskyi\RichSnippets\Api\Data\SetupState;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\ThemeMarkupState;

/**
 * What the Overview knows of the shop when it asks what wants fixing
 */
class AttentionScope implements AttentionScopeInterface
{
    /**
     * @param int|null $storeId
     * @param SetupState $setupState
     * @param ThemeMarkupState $themeMarkup
     * @param list<SnippetInterface> $snippets
     */
    public function __construct(
        private readonly ?int $storeId,
        private readonly SetupState $setupState,
        private readonly ThemeMarkupState $themeMarkup,
        private readonly array $snippets
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
    public function getThemeMarkup(): ThemeMarkupState
    {
        return $this->themeMarkup;
    }

    /**
     * @inheritDoc
     */
    public function getSnippets(): array
    {
        return $this->snippets;
    }
}
