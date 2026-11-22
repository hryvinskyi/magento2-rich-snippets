<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Config\Backend;

use Hryvinskyi\RichSnippets\Model\Config\CheckTimeExpression;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Refuses a start time of the nightly catalog check that is not `HH:MM`, and writes the cron expression of the time
 * where the check's cron job reads it
 *
 * Surrounding whitespace is dropped first. With the check switched off in the same save, the time is stored as it is
 * and no expression is written: the job does nothing while the check is off, whatever its expression says.
 */
class CheckTime extends Value
{
    /**
     * Where the schedule of the check's cron job is read from
     */
    public const CRON_EXPRESSION_PATH
        = 'crontab/hryvinskyi_rich_snippets/jobs/hryvinskyi_rich_snippets_schedule_check/schedule/cron_expr';

    private const SWITCH_FIELD = 'enabled';
    private const SWITCHED_OFF = '0';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param CheckTimeExpression $expressions
     * @param WriterInterface $configWriter
     * @param AbstractResource|null $resource
     * @param AbstractDb<\Magento\Framework\DataObject>|null $resourceCollection
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly CheckTimeExpression $expressions,
        private readonly WriterInterface $configWriter,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Refuse a time that is not `HH:MM` while the check is on
     *
     * @return $this
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $time = $this->time();
        if (!$this->isSwitchedOff() && $this->expressions->cronExpression($time) === null) {
            throw new ValidatorException(__('Enter a time such as 03:00 or 23:30.'));
        }
        $this->setValue($time);

        return parent::beforeSave();
    }

    /**
     * Write the cron expression of the time while the check is on
     *
     * @return $this
     */
    public function afterSave()
    {
        $expression = $this->expressions->cronExpression($this->time());
        if (!$this->isSwitchedOff() && $expression !== null) {
            $this->configWriter->save(self::CRON_EXPRESSION_PATH, $expression);
        }

        return parent::afterSave();
    }

    /**
     * The submitted time without surrounding whitespace
     *
     * @return string
     */
    private function time(): string
    {
        return trim((string) $this->getValue());
    }

    /**
     * Whether the same save switches the check off
     *
     * @return bool
     */
    private function isSwitchedOff(): bool
    {
        return $this->getFieldsetDataValue(self::SWITCH_FIELD) === self::SWITCHED_OFF;
    }
}
