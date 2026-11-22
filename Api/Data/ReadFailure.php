<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api\Data;

/**
 * Why a storefront page could not be read as a visitor reads it
 *
 * @api
 */
enum ReadFailure: string
{
    /** The store view's own address is not an http or https address */
    case NotHttp = 'not_http';

    /** The storefront did not answer: no connection, no such name, a failed secure handshake */
    case Unreachable = 'unreachable';

    /** The storefront did not answer within the time the test allows */
    case Timeout = 'timeout';

    /** The page is larger than the test reads */
    case TooLarge = 'too_large';

    /** The storefront redirected to an address of another scheme, host or port, which the test does not follow */
    case RedirectElsewhere = 'redirect_elsewhere';

    /** The storefront redirected more often than the test follows */
    case TooManyRedirects = 'too_many_redirects';

    /** The storefront answered with a status other than success; the HTTP status says which */
    case HttpStatus = 'http_status';
}
