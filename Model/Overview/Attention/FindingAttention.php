<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Overview\Attention;

use Hryvinskyi\RichSnippets\Api\AttentionItemProviderInterface;
use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\AttentionScopeInterface;
use Hryvinskyi\RichSnippets\Api\Data\AttentionTargetInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\Data\AttentionTone;
use Hryvinskyi\RichSnippets\Api\Data\FindingInterface;
use Hryvinskyi\RichSnippets\Api\Data\PropertyTone;
use Hryvinskyi\RichSnippets\Api\Data\SetupState;
use Hryvinskyi\RichSnippets\Model\Check\FindingWording;

/**
 * The findings of one tone of the last catalog check, each an item that opens it on the Test page's catalog mode
 *
 * Configured once for the gaps that make pages invalid and once for the recommended values that are missing. An item
 * is titled with the finding's title and its pages ("No image on 2 products") and says why the gap matters as the
 * finding does. Configured for gaps in the shop's data only, a finding about a property the template never writes is
 * left to the editor's checks and the Test page: the catalog has nothing to fill for it. Findings are listed only
 * while the scope is live: with every snippet off, the last check's gaps are not on any page.
 */
class FindingAttention implements AttentionItemProviderInterface
{
    private const ROUTE = 'hryvinskyi_richsnippets/test/index';
    private const KEY_PREFIX = 'finding-';

    /**
     * @param CatalogCheckInterface $catalogCheck
     * @param FindingWording $wording
     * @param AttentionItemInterfaceFactory $items
     * @param AttentionTargetInterfaceFactory $targets
     * @param string $findingTone the tone of the findings listed (`bad`, `warn`)
     * @param string $itemTone the tone of their items (`bad`, `warn`, `info`)
     * @param bool $dataGapsOnly whether only findings of a value the template reads and found empty are listed
     */
    public function __construct(
        private readonly CatalogCheckInterface $catalogCheck,
        private readonly FindingWording $wording,
        private readonly AttentionItemInterfaceFactory $items,
        private readonly AttentionTargetInterfaceFactory $targets,
        private readonly string $findingTone,
        private readonly string $itemTone,
        private readonly bool $dataGapsOnly = false
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws \ValueError when a configured tone is none of the tones
     */
    public function items(AttentionScopeInterface $scope): array
    {
        if ($scope->getSetupState() !== SetupState::Live) {
            return [];
        }
        $findingTone = PropertyTone::from($this->findingTone);
        $itemTone = AttentionTone::from($this->itemTone);
        $storeId = $scope->getStoreId();
        $items = [];
        foreach ($this->catalogCheck->findings($storeId) as $finding) {
            if ($finding->getTone() !== $findingTone || ($this->dataGapsOnly && $finding->getSources() === [])) {
                continue;
            }
            $items[] = $this->items->create([
                'key' => self::KEY_PREFIX . $finding->getKey(),
                'tone' => $itemTone,
                'title' => '%1 on %2',
                'titleArguments' => [
                    $this->wording->title($finding),
                    $this->wording->count($finding, $finding->getPages()),
                ],
                'description' => '%1',
                'descriptionArguments' => [$this->wording->description($finding)],
                'actionLabel' => 'See them',
                'target' => $this->targets->create([
                    'route' => self::ROUTE,
                    'params' => $this->params($finding, $storeId),
                ]),
            ]);
        }

        return $items;
    }

    /**
     * The Test page's parameters that open the finding in the scope's store view
     *
     * @param FindingInterface $finding
     * @param int|null $storeId
     * @return array<string,string>
     */
    private function params(FindingInterface $finding, ?int $storeId): array
    {
        $params = ['mode' => 'catalog', 'finding' => $finding->getKey()];

        return $storeId === null ? $params : $params + ['store' => (string) $storeId];
    }
}
