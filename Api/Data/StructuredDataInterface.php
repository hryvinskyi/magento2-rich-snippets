<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * The structured data an HTML page carries: its JSON-LD script blocks and its schema.org microdata
 *
 * @api
 */
interface StructuredDataInterface
{
    /**
     * The JSON-LD blocks, in page order
     *
     * @return list<JsonLdBlockInterface>
     */
    public function getBlocks(): array;

    /**
     * The schema.org items described with microdata, in page order
     *
     * @return list<MicrodataItemInterface>
     */
    public function getMicrodata(): array;
}
