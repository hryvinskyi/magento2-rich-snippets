<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * The settings of the catalog check: whether it runs by itself, when it starts, and who gets its findings
 *
 * All are global. A stored list of recipients with an entry that is not an address sends to nobody. The start time
 * is `HH:MM` on the 24-hour clock, as the settings page stores it.
 */
class CheckSettings
{
    private const ENABLED = 'hryvinskyi_rich_snippets/check/enabled';
    private const EMAIL = 'hryvinskyi_rich_snippets/check/email';
    private const TIME = 'hryvinskyi_rich_snippets/check/time';

    /**
     * @param ScopeConfigInterface $config
     * @param RecipientList $recipients
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly RecipientList $recipients
    ) {
    }

    /**
     * Whether the check runs by itself: every night, and after a product import
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isSetFlag(self::ENABLED);
    }

    /**
     * When the nightly check starts, `HH:MM`; empty when no time is stored
     *
     * @return string
     */
    public function startAt(): string
    {
        $time = $this->config->getValue(self::TIME);

        return is_string($time) ? trim($time) : '';
    }

    /**
     * The addresses the findings go to
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        $list = $this->config->getValue(self::EMAIL);

        return $this->recipients->addresses(is_string($list) ? $list : '') ?? [];
    }
}
