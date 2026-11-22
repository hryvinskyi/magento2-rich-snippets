<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Data\Store;

use Hryvinskyi\RichSnippets\Api\EntityDataPartInterface;
use Hryvinskyi\RichSnippets\Model\Price\VisitorPriceContext;
use Hryvinskyi\RichSnippets\Model\Store\StoreAddresses;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Each store view's own values: `name`, `url` (its home page), `logo` (absolute, from its design configuration),
 * `phone`, `email` (the general contact), `currency` (the display currency the visitor sees), `countries` (the list
 * of country codes it sells to) and the parts of its address from Store Information (`address.street`,
 * `address.postcode`, `address.city`, `address.region`, `address.country`); an empty setting is null
 */
class InformationPart implements EntityDataPartInterface
{
    private const PHONE = 'general/store_information/phone';
    private const STREET_LINES = ['general/store_information/street_line1', 'general/store_information/street_line2'];
    private const POSTCODE = 'general/store_information/postcode';
    private const CITY = 'general/store_information/city';
    private const REGION = 'general/store_information/region_id';
    private const COUNTRY = 'general/store_information/country_id';
    private const EMAIL = 'trans_email/ident_general/email';
    private const LOGO = 'design/header/logo_src';
    private const LOGO_MEDIA = 'logo/';
    private const COUNTRIES = 'general/country/allow';

    /**
     * @param ScopeConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param StoreAddresses $addresses
     * @param VisitorPriceContext $visitor
     * @param CountryInformationAcquirerInterface $countries
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly StoreAddresses $addresses,
        private readonly VisitorPriceContext $visitor,
        private readonly CountryInformationAcquirerInterface $countries
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(array $ids, int $storeId, array $variablePaths, array $collectionLimits): array
    {
        $entries = [];
        foreach ($ids as $id) {
            $store = $this->storeManager->getStore($id);
            $logo = $this->setting(self::LOGO, $id);
            $country = $this->setting(self::COUNTRY, $id);
            $street = array_filter(
                array_map(fn (string $path): ?string => $this->setting($path, $id), self::STREET_LINES)
            );
            $countries = array_values(array_filter(explode(',', (string) $this->setting(self::COUNTRIES, $id))));
            $name = $store instanceof Store ? (string) $store->getFrontendName() : (string) $store->getName();
            $entries[$id] = [
                'name' => $name !== '' ? $name : null,
                'url' => $this->addresses->page('', $id),
                'logo' => $logo !== null ? $this->addresses->media(self::LOGO_MEDIA . ltrim($logo, '/'), $id) : null,
                'phone' => $this->setting(self::PHONE, $id),
                'email' => $this->setting(self::EMAIL, $id),
                'currency' => $this->visitor->currencyCode($id),
                'countries' => $countries !== [] ? $countries : null,
                'address.street' => $street !== [] ? implode(', ', $street) : null,
                'address.postcode' => $this->setting(self::POSTCODE, $id),
                'address.city' => $this->setting(self::CITY, $id),
                'address.region' => $this->region($this->setting(self::REGION, $id), $country),
                'address.country' => $country,
            ];
        }

        return $entries;
    }

    /**
     * A store view's setting; null when empty
     *
     * @param string $path
     * @param int $storeId
     * @return string|null
     */
    private function setting(string $path, int $storeId): ?string
    {
        $value = $this->config->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /**
     * The region's name: a region of the country picked from the list, or the text typed in
     *
     * @param string|null $region
     * @param string|null $country
     * @return string|null
     */
    private function region(?string $region, ?string $country): ?string
    {
        if ($region === null || !ctype_digit($region)) {
            return $region;
        }
        if ($country === null) {
            return null;
        }
        try {
            foreach ($this->countries->getCountryInfo($country)->getAvailableRegions() ?? [] as $known) {
                if ((string) $known->getId() === $region) {
                    return (string) $known->getName();
                }
            }
        } catch (NoSuchEntityException) {
            return null;
        }

        return null;
    }
}
