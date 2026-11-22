<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * A delete refused because other snippets include the snippet
 *
 * Any snippet can be included, a page snippet as much as a building block, so a delete is refused whenever another
 * snippet's template names the code; the includers are listed so the admin can say which ones.
 *
 * @api
 */
class SnippetInUseException extends \DomainException
{
    /**
     * @param string $snippetCode the code of the snippet that was not deleted
     * @param array<string,string> $includers the names of the snippets that include it, by code
     */
    public function __construct(private readonly string $snippetCode, private readonly array $includers)
    {
        parent::__construct(
            'The snippet "' . $snippetCode . '" is included by ' . implode(', ', array_keys($includers))
                . ' and was not deleted.'
        );
    }

    /**
     * The code of the snippet that was not deleted
     *
     * @return string
     */
    public function getSnippetCode(): string
    {
        return $this->snippetCode;
    }

    /**
     * The names of the snippets that include it, by code, in code order
     *
     * @return array<string,string>
     */
    public function getIncluders(): array
    {
        return $this->includers;
    }
}
