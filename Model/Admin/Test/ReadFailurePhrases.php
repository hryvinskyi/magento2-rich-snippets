<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test;

use Hryvinskyi\RichSnippets\Api\Data\ReadFailure;

/**
 * Says why a page could not be read, and that the catalog check still works without the storefront
 *
 * A status the storefront answered is named, with what it usually means when that is known.
 */
class ReadFailurePhrases
{
    /**
     * The explanation of a failed read
     *
     * @param ReadFailure $failure
     * @param int|null $httpStatus
     * @return string
     */
    public function text(ReadFailure $failure, ?int $httpStatus): string
    {
        return $this->cause($failure, $httpStatus) . ' ' . __(
            'The test reads the page as a visitor would, so it needs the storefront to answer. The catalog check'
            . ' does not, and still works.'
        );
    }

    /**
     * What went wrong
     *
     * @param ReadFailure $failure
     * @param int|null $httpStatus
     * @return string
     */
    private function cause(ReadFailure $failure, ?int $httpStatus): string
    {
        return (string) match ($failure) {
            ReadFailure::HttpStatus => $this->status((int) $httpStatus),
            ReadFailure::NotHttp => __('The store view’s address is not an http or https address.'),
            ReadFailure::Unreachable => __('The storefront did not answer this address.'),
            ReadFailure::Timeout => __('The storefront did not answer in time.'),
            ReadFailure::TooLarge => __('The page is larger than the test reads.'),
            ReadFailure::RedirectElsewhere => __(
                'The storefront sent the test to another site, which the test does not follow.'
            ),
            ReadFailure::TooManyRedirects => __('The storefront redirected more times than the test follows.'),
        };
    }

    /**
     * The status the storefront answered, with what it usually means
     *
     * @param int $status
     * @return string
     */
    private function status(int $status): string
    {
        $meaning = match ($status) {
            401, 403 => __('the page needs a login'),
            404 => __('no page has this address'),
            410 => __('the page was removed'),
            500 => __('the shop had an error'),
            503 => __('the shop is in maintenance mode'),
            default => null,
        };

        return (string) ($meaning === null
            ? __('The storefront answered %1 for this address.', $status)
            : __('The storefront answered %1 for this address: %2.', $status, $meaning));
    }
}
