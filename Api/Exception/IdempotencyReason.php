<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Exception;

/**
 * Why the idempotency key of a creating write was refused
 *
 * @api
 */
enum IdempotencyReason: string
{
    /** The key is empty, longer than 64 characters or holds a character outside letters, digits, `-`, `_`, `.`, `:` */
    case KeyInvalid = 'key_invalid';

    /** The key was used before for another kind of write; parameters `key`, `action` */
    case KeyReused = 'key_reused';
}
