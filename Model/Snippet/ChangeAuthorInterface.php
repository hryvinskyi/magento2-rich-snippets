<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

/**
 * Who is saving a snippet, for the "changed by" of the snippet list
 */
interface ChangeAuthorInterface
{
    /**
     * The user name of the admin saving; empty when no admin is (a cron job, a command, a setup patch)
     *
     * @return string
     */
    public function name(): string;
}
