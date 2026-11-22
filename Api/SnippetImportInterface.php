<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Api;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\ImportChoice;
use Hryvinskyi\RichSnippets\Api\Data\ImportedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\ImportPreviewInterface;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetImportException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;

/**
 * Reads a file SnippetExportInterface wrote and brings its snippets into the shop, asking first
 *
 * The file is at most 2 MB and nests at most 64 levels. Its snippets are matched with the shop's by code. Each one
 * goes through the rules a save applies (code, name, type, a template that compiles, known kinds of page and
 * condition fields), and the snippets of the shop and of the file together must not include themselves. Store
 * views are resolved by code and condition values by their label, never by a row ID from the other shop; a store
 * view that cannot be resolved is left out, and a snippet left with none, or with a condition that cannot be
 * resolved, cannot be added. A snippet that is added arrives switched off; a replaced one keeps its switch, its
 * store views and its place in the list. The commit reads the file again and checks everything again: it never
 * trusts what a preview said.
 *
 * @api
 */
interface SnippetImportInterface
{
    /**
     * What importing a file would do
     *
     * @param string $file the file's text
     * @return ImportPreviewInterface
     * @throws SnippetImportException when the file is not an export this module can read
     */
    public function preview(string $file): ImportPreviewInterface;

    /**
     * Import a file, each snippet as chosen, all or nothing
     *
     * A commit carries an idempotency key generated once per intent: a request repeated with it imports nothing more
     * and answers what the first one did.
     *
     * @param string $file the file's text
     * @param array<string,ImportChoice> $choices by the code of a snippet in the file; a snippet without a choice is
     *     skipped
     * @param string $idempotencyKey
     * @return list<ImportedSnippetInterface> in the file's order
     * @throws SnippetImportException when the file cannot be read, a choice is not offered or the snippets chosen
     *     would include themselves
     * @throws IdempotencyException when the key is invalid or was used for another kind of write
     * @throws SnippetRuleException|CompileException when the shop changed since the file was checked and a snippet
     *     no longer passes a rule
     */
    public function commit(string $file, array $choices, string $idempotencyKey): array;
}
