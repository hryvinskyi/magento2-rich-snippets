<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\SnippetListInterface;

/**
 * Answers the snippet list for the store view a request names (see SnippetRowsPresenter for the answer's shape)
 *
 * @phpstan-import-type Listing from SnippetRowsPresenter
 */
class SnippetsListing
{
    /**
     * @param SnippetsRequest $request
     * @param SnippetListInterface $snippets
     * @param SnippetRowsPresenter $presenter
     */
    public function __construct(
        private readonly SnippetsRequest $request,
        private readonly SnippetListInterface $snippets,
        private readonly SnippetRowsPresenter $presenter
    ) {
    }

    /**
     * The list for the store view the values name
     *
     * @param array<mixed> $values the query's parameters or the body's fields
     * @return Listing
     * @throws InvalidWizardRequestException
     */
    public function of(array $values): array
    {
        return $this->presenter->present($this->snippets->list($this->request->storeId($values)));
    }
}
