<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Price;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Model\Calculation;

/**
 * The tax rates a product tax class is charged at in a store view, for the visitor's customer group
 *
 * The destination is the store view's default tax destination, never the visitor's address; the origin is the
 * store's shipping origin, which a catalog entered including tax was priced at. Each rate is looked up once per
 * process.
 */
class TaxRates
{
    /**
     * @var array<string, array{destination: float, origin: float}>
     */
    private array $rates = [];

    /**
     * @var array<int, int|null> the customer tax class of each customer group
     */
    private array $customerClasses = [];

    /**
     * @param Calculation $calculation
     * @param GroupRepositoryInterface $groups
     * @param VisitorPriceContext $visitor
     */
    public function __construct(
        private readonly Calculation $calculation,
        private readonly GroupRepositoryInterface $groups,
        private readonly VisitorPriceContext $visitor
    ) {
    }

    /**
     * The rates in percent at the default destination and at the store's origin
     *
     * @param int $productTaxClassId
     * @param int $storeId
     * @return array{destination: float, origin: float}
     */
    public function rates(int $productTaxClassId, int $storeId): array
    {
        $groupId = $this->visitor->customerGroupId();
        $key = $storeId . ':' . $groupId . ':' . $productTaxClassId;
        if (isset($this->rates[$key])) {
            return $this->rates[$key];
        }
        if ($productTaxClassId === 0) {
            return $this->rates[$key] = ['destination' => 0.0, 'origin' => 0.0];
        }
        $request = $this->calculation->getRateRequest(null, null, $this->customerClass($groupId), $storeId);
        $request->setData('product_class_id', $productTaxClassId);

        return $this->rates[$key] = [
            'destination' => (float) $this->calculation->getRate($request),
            'origin' => (float) $this->calculation->getStoreRate($request, $storeId),
        ];
    }

    /**
     * The customer tax class of a customer group; null for a group that does not exist
     *
     * @param int $groupId
     * @return int|null
     */
    private function customerClass(int $groupId): ?int
    {
        if (!array_key_exists($groupId, $this->customerClasses)) {
            try {
                $this->customerClasses[$groupId] = (int) $this->groups->getById($groupId)->getTaxClassId();
            } catch (NoSuchEntityException) {
                $this->customerClasses[$groupId] = null;
            }
        }

        return $this->customerClasses[$groupId];
    }
}
