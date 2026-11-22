<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * A requirement rule definition that cannot be read or does not have the shape a rule needs
 *
 * Raised when the rules are loaded, so a broken rule file stops the load with its code and the offending key instead
 * of surfacing as a wrong verdict later.
 *
 * @api
 */
class MalformedRuleException extends \UnexpectedValueException
{
}
