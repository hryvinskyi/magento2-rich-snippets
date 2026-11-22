<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * An import refused as a whole, with the reason and the values it refers to; nothing was changed
 *
 * @api
 */
class SnippetImportException extends \DomainException
{
    /**
     * @param ImportReason $reason
     * @param array<string,string> $params the values the reason refers to, by name
     * @param \Throwable|null $previous
     */
    public function __construct(
        private readonly ImportReason $reason,
        private readonly array $params = [],
        ?\Throwable $previous = null
    ) {
        $details = [];
        foreach ($params as $name => $value) {
            $details[] = $name . '=' . $value;
        }

        parent::__construct(
            'The import is refused: ' . $reason->value . ($details === [] ? '' : ' (' . implode(', ', $details) . ')'),
            0,
            $previous
        );
    }

    /**
     * Why the import was refused
     *
     * @return ImportReason
     */
    public function getReason(): ImportReason
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
