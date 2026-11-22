<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

/**
 * The kinds of page and the condition fields a snippet's placement may name
 *
 * @api
 */
interface PlacementVocabularyInterface
{
    /**
     * Whether a kind of page has the code
     *
     * @param string $kind
     * @return bool
     */
    public function hasPageKind(string $kind): bool;

    /**
     * The kind of page a condition field is offered for
     *
     * @param string $field the condition field's code
     * @return string|null null when no condition field has the code
     */
    public function pageKindOfField(string $field): ?string;
}
