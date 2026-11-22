<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Engine;

use Hryvinskyi\JsonTemplate\Api\TimezoneProviderInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The `date` filter's time zone: the current store view's
 */
class StoreTimezone implements TimezoneProviderInterface
{
    /**
     * @param TimezoneInterface $timezone
     * @param StoreManagerInterface $storeManager
     * @param \DateTimeZoneFactory $zones
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly StoreManagerInterface $storeManager,
        private readonly \DateTimeZoneFactory $zones
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getTimezone(): \DateTimeZone
    {
        $name = $this->timezone->getConfigTimezone(
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getCode()
        );

        return $this->zones->create(['timezone' => $name !== '' ? $name : 'UTC']);
    }
}
