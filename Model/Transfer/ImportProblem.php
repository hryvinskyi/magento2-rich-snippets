<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer;

use Hryvinskyi\RichSnippets\Api\Data\ImportProblemInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportProblemReason;

/**
 * A problem with a snippet of an import file
 */
class ImportProblem implements ImportProblemInterface
{
    /**
     * @param ImportProblemReason $reason
     * @param array<string,string> $params
     */
    public function __construct(
        private readonly ImportProblemReason $reason,
        private readonly array $params
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getReason(): ImportProblemReason
    {
        return $this->reason;
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
