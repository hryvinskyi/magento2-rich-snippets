<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Check;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\CatalogCheckInterface;
use Hryvinskyi\RichSnippets\Api\Data\CheckTrigger;
use Hryvinskyi\RichSnippets\Api\Data\FindingsCsvInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsRequest;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Answers the Test page's "Whole catalog" mode: where the check stands, "Check now", the findings, the pages behind
 * one and their download
 *
 * A store view is `store=<id>`, every store view when left out or `all`; a finding is `key=<key>`; a part of its
 * list `page=<n>`, the first when left out. A key the last check did not find, a key that is not one, or a part
 * below 1 is refused (answered 400).
 *
 * @phpstan-import-type Answer from CheckStatusPresenter
 * @phpstan-import-type Row from FindingRows
 * @phpstan-import-type Item from FindingRows
 */
class CheckAnswers
{
    public const KEY = 'key';
    public const PAGE = 'page';
    private const KEY_PATTERN = '/\A[a-z0-9][a-z0-9-]{0,190}\z/';

    /**
     * @param CatalogCheckInterface $catalogCheck
     * @param CheckStatusPresenter $status
     * @param FindingRows $rows
     * @param SnippetsRequest $request
     * @param SnippetRepositoryInterface $snippets
     * @param int $pageSize the pages of a finding listed at a time, as the catalog check lists them
     */
    public function __construct(
        private readonly CatalogCheckInterface $catalogCheck,
        private readonly CheckStatusPresenter $status,
        private readonly FindingRows $rows,
        private readonly SnippetsRequest $request,
        private readonly SnippetRepositoryInterface $snippets,
        private readonly int $pageSize = 8
    ) {
    }

    /**
     * Where the check stands
     *
     * @return Answer
     */
    public function status(): array
    {
        return $this->status->present($this->catalogCheck->status());
    }

    /**
     * Ask for a check now, then where the check stands
     *
     * @return Answer
     */
    public function request(): array
    {
        $this->catalogCheck->request(CheckTrigger::Manual);

        return $this->status();
    }

    /**
     * The findings of the last check
     *
     * @param array<mixed> $query
     * @return array{store: int|null, subtitle: string, findings: list<Row>}
     * @throws InvalidWizardRequestException
     */
    public function findings(array $query): array
    {
        $storeId = $this->request->storeId($query);

        return $this->rows->table($this->catalogCheck->findings($storeId), $storeId, $this->names());
    }

    /**
     * Part of the pages behind a finding
     *
     * @param array<mixed> $query
     * @return array{finding: Row, total: int, page: int, pageSize: int, note: string|null, items: list<Item>}
     * @throws InvalidWizardRequestException
     */
    public function items(array $query): array
    {
        $page = $this->page($query);
        try {
            $items = $this->catalogCheck->findingItems($this->key($query), $this->request->storeId($query), $page);
        } catch (NoSuchEntityException $e) {
            throw new InvalidWizardRequestException(self::KEY, 'The last check found nothing with this key.', $e);
        }

        return $this->rows->items($items, $page, $this->pageSize, $this->names());
    }

    /**
     * Every page behind a finding, as a CSV file
     *
     * @param array<mixed> $query
     * @return FindingsCsvInterface
     * @throws InvalidWizardRequestException
     */
    public function csv(array $query): FindingsCsvInterface
    {
        try {
            return $this->catalogCheck->exportCsv($this->key($query), $this->request->storeId($query));
        } catch (NoSuchEntityException $e) {
            throw new InvalidWizardRequestException(self::KEY, 'The last check found nothing with this key.', $e);
        }
    }

    /**
     * The finding's key the query names
     *
     * @param array<mixed> $query
     * @return string
     * @throws InvalidWizardRequestException
     */
    private function key(array $query): string
    {
        $key = $query[self::KEY] ?? null;
        if (!is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidWizardRequestException(self::KEY, 'The request names no finding.');
        }

        return $key;
    }

    /**
     * The part of a list the query asks for
     *
     * @param array<mixed> $query
     * @return int
     * @throws InvalidWizardRequestException
     */
    private function page(array $query): int
    {
        $page = $query[self::PAGE] ?? 1;
        if (is_string($page) && ctype_digit($page)) {
            $page = (int) $page;
        }
        if (!is_int($page) || $page < 1) {
            throw new InvalidWizardRequestException(self::PAGE, 'The request names no part of the list.');
        }

        return $page;
    }

    /**
     * Every snippet's name by its code
     *
     * @return array<string, string>
     */
    private function names(): array
    {
        $names = [];
        foreach ($this->snippets->list() as $snippet) {
            $names[$snippet->getCode()] = $snippet->getName();
        }

        return $names;
    }
}
