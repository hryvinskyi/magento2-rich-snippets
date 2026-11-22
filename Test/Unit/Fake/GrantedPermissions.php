<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\AdminUi\Api\Page\PagePermissionsInterface;

/**
 * The permissions of an admin as a test sets them
 */
class GrantedPermissions implements PagePermissionsInterface
{
    /**
     * @param array<string, bool> $granted whether the admin has each permission, by name
     */
    public function __construct(private readonly array $granted = ['snippets' => true, 'test' => true])
    {
    }

    /**
     * @inheritDoc
     */
    public function granted(): array
    {
        return $this->granted;
    }
}
