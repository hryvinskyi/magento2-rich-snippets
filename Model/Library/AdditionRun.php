<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Library;

use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;

/**
 * What one add from the library knows while it runs: the names in use, the shop's building blocks by library code,
 * and the snippets the add ended with, each listed once
 *
 * Every add takes a run of its own from the factory.
 */
class AdditionRun
{
    /**
     * @var array<string, true> the names in use
     */
    private array $names = [];

    /**
     * @var array<string, SnippetInterface> the shop's snippet for each building block, by library code
     */
    private array $blocks = [];

    /**
     * @var list<array{id: int, code: string, name: string, block: bool, template: string, reused: bool}>
     */
    private array $records = [];

    /**
     * @var array<string, true> the codes of the snippets listed in the records
     */
    private array $recorded = [];

    /**
     * Take in a snippet the shop has
     *
     * A snippet added from a template stands for that template's building block, a building block before a page
     * snippet, else the first one taken in.
     *
     * @param SnippetInterface $snippet
     * @return void
     */
    public function know(SnippetInterface $snippet): void
    {
        $this->names[$snippet->getName()] = true;
        $origin = $snippet->getOrigin()?->getCode();
        if ($origin === null) {
            return;
        }
        $known = $this->blocks[$origin] ?? null;
        if ($known === null || (!$known->isBlock() && $snippet->isBlock())) {
            $this->blocks[$origin] = $snippet;
        }
    }

    /**
     * The shop's snippet for a building block's library code; null when it has none
     *
     * @param string $code
     * @return SnippetInterface|null
     */
    public function blockOf(string $code): ?SnippetInterface
    {
        return $this->blocks[$code] ?? null;
    }

    /**
     * The names in use
     *
     * @return array<string, true>
     */
    public function takenNames(): array
    {
        return $this->names;
    }

    /**
     * Note a snippet the add created from a template
     *
     * @param SnippetInterface $snippet
     * @param string $template
     * @return void
     */
    public function created(SnippetInterface $snippet, string $template): void
    {
        $this->names[$snippet->getName()] = true;
        if ($snippet->isBlock()) {
            $this->blocks[$template] = $snippet;
        }
        $this->record($snippet, $template, false);
    }

    /**
     * Note a building block the add reused; one the add created stays noted as created
     *
     * @param SnippetInterface $snippet
     * @param string $template
     * @return void
     */
    public function reused(SnippetInterface $snippet, string $template): void
    {
        $this->record($snippet, $template, true);
    }

    /**
     * The snippets the add ended with, in the order it met them
     *
     * @return list<array{id: int, code: string, name: string, block: bool, template: string, reused: bool}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * List a snippet once
     *
     * @param SnippetInterface $snippet
     * @param string $template
     * @param bool $reused
     * @return void
     */
    private function record(SnippetInterface $snippet, string $template, bool $reused): void
    {
        if (isset($this->recorded[$snippet->getCode()])) {
            return;
        }
        $this->recorded[$snippet->getCode()] = true;
        $this->records[] = [
            LibraryAddition::ID => (int) $snippet->getId(),
            LibraryAddition::CODE => $snippet->getCode(),
            LibraryAddition::NAME => $snippet->getName(),
            LibraryAddition::BLOCK => $snippet->isBlock(),
            LibraryAddition::TEMPLATE => $template,
            LibraryAddition::REUSED => $reused,
        ];
    }
}
