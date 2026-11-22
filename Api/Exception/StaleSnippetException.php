<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * A save refused because the snippet was saved by someone else after the version the save was made from
 *
 * Nothing was written; the stored snippet is as the other save left it.
 *
 * @api
 */
class StaleSnippetException extends \DomainException
{
    /**
     * @param int $snippetId
     * @param string $updatedAt when the stored snippet was saved last, as the database stores it (`Y-m-d H:i:s`)
     * @param string $updatedBy the user name of the admin who saved it last; empty for a save without one
     */
    public function __construct(
        private readonly int $snippetId,
        private readonly string $updatedAt,
        private readonly string $updatedBy
    ) {
        parent::__construct(sprintf('The snippet %d was saved again at %s.', $snippetId, $updatedAt));
    }

    /**
     * The snippet that was not saved
     *
     * @return int
     */
    public function getSnippetId(): int
    {
        return $this->snippetId;
    }

    /**
     * When the stored snippet was saved last, as the database stores it (`Y-m-d H:i:s`, UTC)
     *
     * @return string
     */
    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    /**
     * The user name of the admin who saved the stored snippet last; empty for a save without one
     *
     * @return string
     */
    public function getUpdatedBy(): string
    {
        return $this->updatedBy;
    }
}
