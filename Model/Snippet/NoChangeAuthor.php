<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

/**
 * The author outside the admin: nobody
 */
class NoChangeAuthor implements ChangeAuthorInterface
{
    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return '';
    }
}
