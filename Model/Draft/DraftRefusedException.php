<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\RichSnippets\Api\Data\DraftProblemInterface;

/**
 * A new draft refused before anything was claimed or written, with the problems that refused it
 *
 * Thrown while a creating write prepares, so the idempotency key stays unused and the corrected draft can be saved
 * with it.
 */
class DraftRefusedException extends \DomainException
{
    /**
     * @param list<DraftProblemInterface> $problems at least one
     */
    public function __construct(private readonly array $problems)
    {
        parent::__construct('The draft breaks a rule that stops it from being saved.');
    }

    /**
     * The problems that refused the draft
     *
     * @return list<DraftProblemInterface>
     */
    public function getProblems(): array
    {
        return $this->problems;
    }
}
