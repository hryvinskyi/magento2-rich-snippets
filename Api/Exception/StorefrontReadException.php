<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

use Hryvinskyi\RichSnippets\Api\Data\ReadFailure;

/**
 * A storefront page that could not be read, with the reason and, when the storefront answered, its HTTP status
 *
 * @api
 */
class StorefrontReadException extends \RuntimeException
{
    /**
     * @param ReadFailure $reason
     * @param int|null $httpStatus the status of the last answer; null when there was none
     * @param \Throwable|null $previous
     */
    public function __construct(
        private readonly ReadFailure $reason,
        private readonly ?int $httpStatus = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            'The storefront page could not be read: ' . $reason->value
            . ($httpStatus === null ? '' : ' (HTTP ' . $httpStatus . ')'),
            0,
            $previous
        );
    }

    /**
     * Why the page could not be read
     *
     * @return ReadFailure
     */
    public function getReason(): ReadFailure
    {
        return $this->reason;
    }

    /**
     * The HTTP status of the last answer; null when the storefront gave none
     *
     * @return int|null
     */
    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }
}
