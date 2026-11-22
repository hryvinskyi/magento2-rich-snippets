<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Api\ResultTypeRuleSourceInterface;

/**
 * A rule source that hands over a definition it was given, counting how often it was read
 */
class DefinitionSource implements ResultTypeRuleSourceInterface
{
    /**
     * @var int
     */
    public int $reads = 0;

    /**
     * @param array<mixed> $definition
     */
    public function __construct(private readonly array $definition)
    {
    }

    /**
     * @inheritDoc
     */
    public function read(): array
    {
        $this->reads++;

        return $this->definition;
    }
}
