<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\RichSnippets\Api\Data\ResultTypeRuleInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;

/**
 * Every kind of Google result the module knows, each with its requirement rule
 *
 * The rules are data a module ships (ResultTypeRuleSourceInterface), so a change on Google's side is a data update.
 * Every definition is validated when the rules are loaded: one that is malformed fails the load.
 *
 * @api
 */
interface ResultTypeRulePoolInterface
{
    /**
     * Every rule, in the pool's order
     *
     * @return list<ResultTypeRuleInterface>
     * @throws MalformedRuleException when a definition is malformed
     */
    public function all(): array;

    /**
     * The rule of a code
     *
     * @param string $code
     * @return ResultTypeRuleInterface|null null when no rule has the code
     * @throws MalformedRuleException when a definition is malformed
     */
    public function get(string $code): ?ResultTypeRuleInterface;

    /**
     * The rules that apply to a block of a schema.org type, at its root or at an object inside it, in the pool's order
     *
     * @param string $type
     * @return list<ResultTypeRuleInterface> none for a type Google has no result for
     * @throws MalformedRuleException when a definition is malformed
     */
    public function forType(string $type): array;
}
