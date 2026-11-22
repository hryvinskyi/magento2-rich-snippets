<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryTemplateInterface;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryReason;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;
use Hryvinskyi\RichSnippets\Api\LibraryTemplatePoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Idempotency\IdempotentWriteInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The library: its templates with what the shop has of them, their questions, and adds that run once per key
 *
 * The recommended templates are configured in di.xml; adding them skips those the shop has a copy of already.
 */
class Library implements LibraryInterface
{
    private const ACTION_ADD = 'library_add';
    private const ACTION_ADD_RECOMMENDED = 'library_add_recommended';

    /**
     * @param LibraryTemplatePoolInterface $templates
     * @param SnippetRepositoryInterface $snippets
     * @param AnswerResolver $answers
     * @param LibraryAddition $addition
     * @param IdempotentWriteInterface $idempotentWrite
     * @param LibraryResultFactoryInterface $results
     * @param CopyNames $names
     * @param StoreManagerInterface $storeManager
     * @param array<string,string> $recommended the codes of the recommended templates, in the order they are added
     */
    public function __construct(
        private readonly LibraryTemplatePoolInterface $templates,
        private readonly SnippetRepositoryInterface $snippets,
        private readonly AnswerResolver $answers,
        private readonly LibraryAddition $addition,
        private readonly IdempotentWriteInterface $idempotentWrite,
        private readonly LibraryResultFactoryInterface $results,
        private readonly CopyNames $names,
        private readonly StoreManagerInterface $storeManager,
        private readonly array $recommended = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function list(): array
    {
        $versions = [];
        $names = [];
        foreach ($this->snippets->list() as $snippet) {
            $names[$snippet->getName()] = true;
            $origin = $snippet->getOrigin();
            if ($origin !== null) {
                $versions[$origin->getCode()][$origin->getVersion()] = $origin->getVersion();
            }
        }
        $entries = [];
        foreach ($this->templates->all() as $template) {
            $added = array_values($versions[$template->getCode()] ?? []);
            sort($added);
            $entries[] = $this->results->entry(
                $template,
                $added,
                array_values(array_filter(
                    $template->getUses(),
                    static fn (string $use): bool => isset($versions[$use])
                )),
                $this->names->next($template->getName(), $names)
            );
        }

        return $entries;
    }

    /**
     * @inheritDoc
     */
    public function recommended(): array
    {
        return array_values(array_map('strval', $this->recommended));
    }

    /**
     * @inheritDoc
     */
    public function suggestions(string $code): array
    {
        return $this->answers->suggestions($this->template($code), $this->storeId());
    }

    /**
     * @inheritDoc
     */
    public function add(string $code, array $answers, string $idempotencyKey): array
    {
        $template = $this->template($code);
        $storeId = $this->storeId();

        return $this->addedSnippets($this->idempotentWrite->once(
            $idempotencyKey,
            self::ACTION_ADD,
            function () use ($template, $answers, $storeId): callable {
                $chosen = $this->answers->chosen($template, $answers, $storeId);

                return fn (): array => $this->addition->add([[$template, $chosen]], $storeId);
            }
        ));
    }

    /**
     * @inheritDoc
     */
    public function addRecommended(string $idempotencyKey): array
    {
        $storeId = $this->storeId();

        return $this->addedSnippets($this->idempotentWrite->once(
            $idempotencyKey,
            self::ACTION_ADD_RECOMMENDED,
            function () use ($storeId): callable {
                $requests = [];
                foreach ($this->missingRecommended() as $template) {
                    $requests[] = [$template, $this->answers->chosen($template, [], $storeId)];
                }

                return fn (): array => $this->addition->add($requests, $storeId);
            }
        ));
    }

    /**
     * The recommended templates the shop has no copy of
     *
     * @return list<LibraryTemplateInterface>
     */
    private function missingRecommended(): array
    {
        $added = [];
        foreach ($this->snippets->list() as $snippet) {
            $origin = $snippet->getOrigin();
            if ($origin !== null) {
                $added[$origin->getCode()] = true;
            }
        }
        $requests = [];
        foreach ($this->recommended as $code) {
            if (!isset($added[$code])) {
                $requests[] = $this->template($code);
            }
        }

        return $requests;
    }

    /**
     * The template of a code
     *
     * @param string $code
     * @return LibraryTemplateInterface
     * @throws LibraryException when no template has the code
     */
    private function template(string $code): LibraryTemplateInterface
    {
        return $this->templates->get($code)
            ?? throw new LibraryException(LibraryReason::TemplateUnknown, ['template' => $code]);
    }

    /**
     * The added snippets of the records an add answered
     *
     * @param array<mixed> $records
     * @return list<AddedSnippetInterface>
     */
    private function addedSnippets(array $records): array
    {
        $added = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $added[] = $this->results->added(
                is_numeric($record[LibraryAddition::ID] ?? null) ? (int) $record[LibraryAddition::ID] : 0,
                is_string($record[LibraryAddition::CODE] ?? null) ? $record[LibraryAddition::CODE] : '',
                is_string($record[LibraryAddition::NAME] ?? null) ? $record[LibraryAddition::NAME] : '',
                ($record[LibraryAddition::BLOCK] ?? false) === true,
                is_string($record[LibraryAddition::TEMPLATE] ?? null) ? $record[LibraryAddition::TEMPLATE] : '',
                ($record[LibraryAddition::REUSED] ?? false) === true
            );
        }

        return $added;
    }

    /**
     * The store view the suggestions and fill counts are those of: the default store view
     *
     * @return int
     */
    private function storeId(): int
    {
        return (int) $this->storeManager->getDefaultStoreView()?->getId();
    }
}
