<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reads what the Snippets page's requests carry, refusing a value of the wrong shape with the name of the value as
 * the reason (answered 400)
 *
 * A store view is `store=<id>` in the query or `"store": id` in a body; left out, empty or `all` it is every store
 * view.
 */
class SnippetsRequest
{
    public const STORE = 'store';
    public const ID = 'id';
    public const ACTIVE = 'active';
    public const IDEMPOTENCY_KEY = 'idempotencyKey';
    public const FILE = 'file';
    public const CHOICES = 'choices';

    private const ALL_STORES = 'all';

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * The store view asked for; null for every store view
     *
     * @param array<mixed> $values the query's parameters or the body's fields
     * @return int|null
     * @throws InvalidWizardRequestException when it names no store view of the shop
     */
    public function storeId(array $values): ?int
    {
        $store = $values[self::STORE] ?? null;
        if ($store === null || $store === '' || $store === self::ALL_STORES) {
            return null;
        }
        $id = is_int($store) || (is_string($store) && ctype_digit($store)) ? (int) $store : null;
        foreach ($this->storeManager->getStores() as $view) {
            if ((int) $view->getId() === $id) {
                return $id;
            }
        }

        throw new InvalidWizardRequestException(self::STORE, 'The request names no store view of the shop.');
    }

    /**
     * The snippet the request is about
     *
     * @param array<mixed> $values
     * @return int
     * @throws InvalidWizardRequestException when there is no positive ID
     */
    public function id(array $values): int
    {
        $id = $values[self::ID] ?? null;
        if (is_string($id) && ctype_digit($id)) {
            $id = (int) $id;
        }
        if (!is_int($id) || $id < 1) {
            throw new InvalidWizardRequestException(self::ID, 'The request names no snippet.');
        }

        return $id;
    }

    /**
     * The snippet to export; null for every snippet
     *
     * @param array<mixed> $values
     * @return int|null
     * @throws InvalidWizardRequestException when an ID is given that is not positive
     */
    public function exportId(array $values): ?int
    {
        return ($values[self::ID] ?? '') === '' ? null : $this->id($values);
    }

    /**
     * The state the switch is wanted in
     *
     * @param array<mixed> $values
     * @return bool
     * @throws InvalidWizardRequestException when it is not true or false
     */
    public function active(array $values): bool
    {
        $active = $values[self::ACTIVE] ?? null;
        if (!is_bool($active)) {
            throw new InvalidWizardRequestException(self::ACTIVE, 'The request does not say whether to switch on.');
        }

        return $active;
    }

    /**
     * The idempotency key of a creating write
     *
     * @param array<mixed> $values
     * @return string
     * @throws InvalidWizardRequestException when there is none
     */
    public function idempotencyKey(array $values): string
    {
        $key = $values[self::IDEMPOTENCY_KEY] ?? null;
        if (!is_string($key) || $key === '') {
            throw new InvalidWizardRequestException('idempotency_key', 'The request carries no idempotency key.');
        }

        return $key;
    }

    /**
     * The text of an import file
     *
     * @param array<mixed> $values
     * @return string
     * @throws InvalidWizardRequestException when there is none
     */
    public function file(array $values): string
    {
        $file = $values[self::FILE] ?? null;
        if (!is_string($file)) {
            throw new InvalidWizardRequestException(self::FILE, 'The request carries no file.');
        }

        return $file;
    }

    /**
     * The choice made for each snippet of an import file
     *
     * @param array<mixed> $values
     * @return array<string, ImportChoice> by code
     * @throws InvalidWizardRequestException when they are not an object of known choices
     */
    public function choices(array $values): array
    {
        $given = $values[self::CHOICES] ?? [];
        if (!is_array($given) || ($given !== [] && array_is_list($given))) {
            throw new InvalidWizardRequestException(self::CHOICES, 'The choices are not an object.');
        }
        $choices = [];
        foreach ($given as $code => $choice) {
            $known = is_string($choice) ? ImportChoice::tryFrom($choice) : null;
            if ($known === null) {
                throw new InvalidWizardRequestException(self::CHOICES, 'A choice is not one an import knows.');
            }
            $choices[(string) $code] = $known;
        }

        return $choices;
    }
}
