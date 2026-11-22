<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet;

use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;

/**
 * The library template a snippet was added from, refused when its code is outside the code grammar or its version
 * is below 1
 */
class Origin implements OriginInterface
{
    /**
     * @param string $code
     * @param int $version
     * @throws SnippetRuleException
     */
    public function __construct(private readonly string $code, private readonly int $version)
    {
        if (preg_match(SnippetInterface::CODE_PATTERN, $code) !== 1 || $version < 1) {
            throw new SnippetRuleException(SnippetRuleReason::OriginInvalid);
        }
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): int
    {
        return $this->version;
    }
}
