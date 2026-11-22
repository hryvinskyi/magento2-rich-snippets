<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Emulation;

use Hryvinskyi\RichSnippets\Api\FrontendEmulationInterface;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Area;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Runs work in the platform's frontend environment emulation of a store view, as a guest in the store view's
 * default display currency
 *
 * The visitor is set in the HTTP context the prices read, and put back as it was afterwards, with the emulation
 * stopped, whether the work returns or throws.
 */
class FrontendEmulation implements FrontendEmulationInterface
{
    private const VISITOR_KEYS = [CustomerContext::CONTEXT_GROUP, HttpContext::CONTEXT_CURRENCY];

    /**
     * @var int|null the store view of the run in progress
     */
    private ?int $running = null;

    /**
     * @param Emulation $emulation
     * @param HttpContext $httpContext
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Emulation $emulation,
        private readonly HttpContext $httpContext,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function run(int $storeId, callable $work): mixed
    {
        if ($this->running !== null) {
            if ($this->running !== $storeId) {
                throw new \LogicException(sprintf(
                    'Store view %d cannot be emulated inside the emulation of store view %d.',
                    $storeId,
                    $this->running
                ));
            }

            return $work();
        }

        $saved = $this->httpContext->toArray();
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        $this->running = $storeId;
        try {
            $this->httpContext->setValue(CustomerContext::CONTEXT_GROUP, GroupInterface::NOT_LOGGED_IN_ID, null);
            $this->httpContext->setValue(HttpContext::CONTEXT_CURRENCY, $this->defaultCurrency($storeId), null);

            return $work();
        } finally {
            $this->restore(is_array($saved['data'] ?? null) ? $saved['data'] : []);
            $this->running = null;
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    /**
     * Put the visitor keys of the HTTP context back as they were
     *
     * @param array<mixed> $data the context's values before the run
     * @return void
     */
    private function restore(array $data): void
    {
        foreach (self::VISITOR_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $this->httpContext->setValue($key, $data[$key], null);
                continue;
            }
            $this->httpContext->unsValue($key);
        }
    }

    /**
     * The store view's default display currency
     *
     * @param int $storeId
     * @return string
     */
    private function defaultCurrency(int $storeId): string
    {
        $store = $this->storeManager->getStore($storeId);

        return $store instanceof Store ? (string) $store->getDefaultCurrencyCode() : '';
    }
}
