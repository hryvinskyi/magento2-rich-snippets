<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition\Operator;

use Hryvinskyi\RichSnippets\Api\ConditionOperatorInterface;
use Hryvinskyi\RichSnippets\Api\ConditionOperatorPoolInterface;

/**
 * The operators listed in the container configuration, by code
 *
 * An item that is not an operator, or one listed under another code than its own, is a configuration error, refused
 * when the pool is built.
 */
class ConditionOperatorPool implements ConditionOperatorPoolInterface
{
    /**
     * @var array<string, ConditionOperatorInterface>
     */
    private array $operators = [];

    /**
     * @param array<array-key,mixed> $operators by code
     * @throws \InvalidArgumentException
     */
    public function __construct(array $operators = [])
    {
        foreach ($operators as $code => $operator) {
            if (!$operator instanceof ConditionOperatorInterface || $operator->getCode() !== (string) $code) {
                throw new \InvalidArgumentException(
                    sprintf('The condition operator "%s" is not an operator with that code.', $code)
                );
            }
            $this->operators[(string) $code] = $operator;
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $code): bool
    {
        return isset($this->operators[$code]);
    }

    /**
     * @inheritDoc
     */
    public function get(string $code): ConditionOperatorInterface
    {
        return $this->operators[$code]
            ?? throw new \InvalidArgumentException(sprintf('No condition operator has the code "%s".', $code));
    }
}
