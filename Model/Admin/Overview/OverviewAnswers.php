<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\OverviewInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsRequest;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Answers the Overview's numbers: `store=<id>` for one store view, every store view when left out or `all`
 *
 * A store view the shop does not have is refused (answered 400).
 *
 * @phpstan-import-type Answer from OverviewPresenter
 */
class OverviewAnswers
{
    /**
     * @param OverviewInterface $overview
     * @param OverviewPresenter $presenter
     * @param SnippetsRequest $request
     */
    public function __construct(
        private readonly OverviewInterface $overview,
        private readonly OverviewPresenter $presenter,
        private readonly SnippetsRequest $request
    ) {
    }

    /**
     * The Overview of the store view the query names
     *
     * @param array<mixed> $query
     * @return Answer
     * @throws InvalidWizardRequestException
     */
    public function read(array $query): array
    {
        $storeId = $this->request->storeId($query);
        try {
            return $this->presenter->present($this->overview->read($storeId));
        } catch (NoSuchEntityException $e) {
            throw new InvalidWizardRequestException(
                SnippetsRequest::STORE,
                'The request names no store view of the shop.',
                $e
            );
        }
    }
}
