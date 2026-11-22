<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\DataObject;

/**
 * The author in the admin: the user name of the admin signed in, empty when none is
 */
class AdminChangeAuthor implements ChangeAuthorInterface
{
    private const USER_NAME = 'username';

    /**
     * @param Session $session
     */
    public function __construct(private readonly Session $session)
    {
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        $user = $this->session->getUser();
        $name = $user instanceof DataObject ? $user->getData(self::USER_NAME) : null;

        return is_string($name) ? $name : '';
    }
}
