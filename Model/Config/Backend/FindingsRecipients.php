<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config\Backend;

use Hryvinskyi\RichSnippets\Model\Config\RecipientList;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Refuses a list of recipients for the catalog check's findings with an entry that is not a complete e-mail address,
 * and stores the addresses separated by a comma and a space
 *
 * An empty list is valid. With the check switched off in the same save, the list is stored as it was typed.
 */
class FindingsRecipients extends Value
{
    private const SWITCH_FIELD = 'enabled';
    private const SWITCHED_OFF = '0';
    private const JOINT = ', ';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param RecipientList $recipients
     * @param AbstractResource|null $resource
     * @param AbstractDb<\Magento\Framework\DataObject>|null $resourceCollection
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly RecipientList $recipients,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Refuse a list with an entry that is not an address while the check is on
     *
     * @return $this
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        if ($this->getFieldsetDataValue(self::SWITCH_FIELD) === self::SWITCHED_OFF) {
            return parent::beforeSave();
        }
        $addresses = $this->recipients->addresses((string) $this->getValue());
        if ($addresses === null) {
            throw new ValidatorException(__('Enter complete e-mail addresses, separated by commas.'));
        }
        $this->setValue(implode(self::JOINT, $addresses));

        return parent::beforeSave();
    }
}
