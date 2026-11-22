<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What saving a draft came to: the snippet as stored, or the problems that stopped the save
 *
 * Exactly one of the two is there: a saved draft has no blocking problem, and a draft that was not saved has at
 * least one. The problems that do not block a save are not repeated here; validating the draft lists them.
 *
 * @api
 */
interface DraftSaveResultInterface
{
    /**
     * The snippet as stored; null when the draft was not saved
     *
     * @return SnippetInterface|null
     */
    public function getSnippet(): ?SnippetInterface;

    /**
     * The blocking problems that stopped the save; empty when it was saved
     *
     * @return list<DraftProblemInterface>
     */
    public function getProblems(): array;
}
