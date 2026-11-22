<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test\Summary;

/**
 * A web site as the name it gives the site
 */
class WebSiteSummary implements BlockSummaryInterface
{
    /**
     * @inheritDoc
     */
    public function summarize(array $object): string
    {
        $name = $object['name'] ?? null;
        if (!is_scalar($name) || trim((string) $name) === '') {
            return '';
        }

        return (string) __('The name of the site: %1.', trim((string) $name));
    }
}
