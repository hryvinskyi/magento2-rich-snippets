<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * A library request that cannot be carried out, with the reason and the values it refers to
 *
 * @api
 */
class LibraryException extends \DomainException
{
    /**
     * @param LibraryReason $reason
     * @param array<string,string> $params the values the reason refers to, by name
     * @param \Throwable|null $previous
     */
    public function __construct(
        private readonly LibraryReason $reason,
        private readonly array $params = [],
        ?\Throwable $previous = null
    ) {
        $details = [];
        foreach ($params as $name => $value) {
            $details[] = $name . '=' . $value;
        }

        parent::__construct(
            'The library refused the request: ' . $reason->value
                . ($details === [] ? '' : ' (' . implode(', ', $details) . ')'),
            0,
            $previous
        );
    }

    /**
     * Why the request was refused
     *
     * @return LibraryReason
     */
    public function getReason(): LibraryReason
    {
        return $this->reason;
    }

    /**
     * The values the reason refers to, by name
     *
     * @return array<string,string>
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
