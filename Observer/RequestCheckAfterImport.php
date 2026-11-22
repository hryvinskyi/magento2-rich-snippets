<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Observer;

use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Model\Check\AutomaticCheck;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * After a product import: asks for a catalog check, unless the check is switched off
 *
 * The import's end is announced once per import; asking while a check is pending adds no run. An import never
 * fails because of this: a failure to ask is logged.
 */
class RequestCheckAfterImport implements ObserverInterface
{
    /**
     * @param AutomaticCheck $automaticCheck
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly AutomaticCheck $automaticCheck,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Ask for the check
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->automaticCheck->request(CheckTrigger::Import);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Rich snippets: no catalog check could be asked for after the import: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
