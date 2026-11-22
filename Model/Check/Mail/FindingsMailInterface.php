<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Mail;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;

/**
 * Tells the recipients of the findings about a finished check that found pages Google would report as invalid
 */
interface FindingsMailInterface
{
    /**
     * Send the e-mail of a finished run, when there is something to say and someone to say it to
     *
     * @param CheckRunInterface $run a finished run
     * @return bool whether an e-mail was sent
     */
    public function afterCheck(CheckRunInterface $run): bool;
}
