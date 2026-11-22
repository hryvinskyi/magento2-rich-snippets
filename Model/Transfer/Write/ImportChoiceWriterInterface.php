<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Transfer\Write;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Model\Transfer\ImportAnalysis;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Writes one snippet of an import file the way one import choice says
 *
 * Registered in the import writer's pool under the choice's value.
 *
 * @phpstan-import-type AnalysedSnippet from ImportAnalysis
 */
interface ImportChoiceWriterInterface
{
    /**
     * Write the snippet
     *
     * @param AnalysedSnippet $analysed
     * @return SnippetInterface|null the snippet as stored; null when nothing was written
     * @throws SnippetRuleException|CompileException
     * @throws NoSuchEntityException when the shop's snippet to replace is gone
     */
    public function write(array $analysed): ?SnippetInterface;
}
