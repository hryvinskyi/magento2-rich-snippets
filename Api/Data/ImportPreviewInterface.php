<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * What an import file holds and what importing it would do, before anything is changed
 *
 * @api
 */
interface ImportPreviewInterface
{
    /**
     * When the file was exported, as its header says; empty when it does not say
     *
     * @return string
     */
    public function getExportedAt(): string;

    /**
     * The host the file was exported from, as its header says; empty when it does not say
     *
     * @return string
     */
    public function getSource(): string;

    /**
     * Each snippet of the file, in the file's order
     *
     * @return list<ImportEntryInterface>
     */
    public function getEntries(): array;
}
