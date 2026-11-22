<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Check\Mail;

use Hryvinskyi\RichSnippets\Api\Data\CheckRunInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckState;
use Hryvinskyi\RichSnippets\Model\Check\AggregateMetric;
use Hryvinskyi\RichSnippets\Model\Check\CheckResultReaderInterface;
use Hryvinskyi\RichSnippets\Model\Config\CheckSettings;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\Store;

/**
 * Sends one e-mail after a finished check that found invalid pages, to the addresses in the settings
 *
 * Nothing is sent while the check is switched off, with no address, or when no page is invalid: pages that only
 * miss a recommended value are counted in the e-mail but never cause one. It is sent from cron, so its template is
 * the admin area's, read for the default scope, from the general contact.
 */
class FindingsMail implements FindingsMailInterface
{
    public const TEMPLATE = 'hryvinskyi_rich_snippets_check_findings';
    private const SENDER = 'general';
    private const FINDINGS_ROUTE = 'hryvinskyi_richsnippets/test/index';

    /**
     * @param CheckSettings $settings
     * @param CheckResultReaderInterface $results
     * @param TransportBuilder $transportBuilder
     * @param UrlInterface $urls
     */
    public function __construct(
        private readonly CheckSettings $settings,
        private readonly CheckResultReaderInterface $results,
        private readonly TransportBuilder $transportBuilder,
        private readonly UrlInterface $urls
    ) {
    }

    /**
     * @inheritDoc
     */
    public function afterCheck(CheckRunInterface $run): bool
    {
        if ($run->getState() !== CheckState::Done || !$this->settings->isEnabled()) {
            return false;
        }
        $recipients = $this->settings->recipients();
        if ($recipients === []) {
            return false;
        }
        $counts = $this->results->aggregates($run->getId(), 0);
        $invalid = array_sum($counts[AggregateMetric::InvalidPages->value] ?? []);
        if ($invalid === 0) {
            return false;
        }

        $this->transportBuilder
            ->setTemplateIdentifier(self::TEMPLATE)
            ->setTemplateOptions(['area' => Area::AREA_ADMINHTML, 'store' => Store::DEFAULT_STORE_ID])
            ->setTemplateVars([
                'invalid' => $invalid,
                'gaps' => array_sum($counts[AggregateMetric::GapPages->value] ?? []),
                'pages' => array_sum($counts[AggregateMetric::Pages->value] ?? []),
                'findings_url' => $this->urls->getUrl(
                    self::FINDINGS_ROUTE,
                    ['_nosecret' => true, '_query' => ['mode' => 'catalog']]
                ),
            ])
            ->setFromByScope(self::SENDER, Store::DEFAULT_STORE_ID)
            ->addTo($recipients)
            ->getTransport()
            ->sendMessage();

        return true;
    }
}
