<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * A library template definition that cannot be read or does not have the shape a template needs
 *
 * Raised when the library is loaded, so a broken definition file stops the load with its code and the offending key
 * instead of surfacing as a broken snippet in a shop later.
 *
 * @api
 */
class MalformedLibraryTemplateException extends \UnexpectedValueException
{
}
