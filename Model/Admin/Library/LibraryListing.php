<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Library;

use Hryvinskyi\RichSnippets\Api\Data\LibraryEntryInterface;
use Hryvinskyi\RichSnippets\Api\Data\LibraryQuestionInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;
use Hryvinskyi\RichSnippets\Api\LibraryTemplatePoolInterface;

/**
 * The library as the Library page shows it: its groups, the recommended set, and every template with what the shop
 * has of it, each text translated into the admin's locale
 *
 * The answer is `{"groups": [{"code", "label"}], "recommended": [code], "templates": [...]}`. Each template is
 * `{"code", "version", "group", "name", "type", "icon", "description", "summary", "where", "isBlock", "google":
 * {"status", "short", "long"}, "properties", "uses": [{"code", "name", "inShop"}], "questions": [{"code", "label"}],
 * "added", "hasNewerVersion", "nextName"}`: `uses` names the building blocks the template includes and whether the
 * shop has one of each already, `nextName` is the name the next copy gets. The recommended set lists only codes the
 * library has, in the order they are added.
 *
 * @phpstan-type Template array{code: string, version: int, group: string, name: string, type: string, icon: string,
 *     description: string, summary: string, where: string, isBlock: bool,
 *     google: array{status: string, short: string, long: string}, properties: list<string>,
 *     uses: list<array{code: string, name: string, inShop: bool}>, questions: list<array{code: string, label: string}>,
 *     added: bool, hasNewerVersion: bool, nextName: string}
 * @phpstan-type Listing array{groups: list<array{code: string, label: string}>, recommended: list<string>,
 *     templates: list<Template>}
 */
class LibraryListing
{
    /**
     * @param LibraryInterface $library
     * @param LibraryTemplatePoolInterface $templates
     */
    public function __construct(
        private readonly LibraryInterface $library,
        private readonly LibraryTemplatePoolInterface $templates
    ) {
    }

    /**
     * The library with what the shop has of it
     *
     * @return Listing
     * @throws MalformedLibraryTemplateException
     */
    public function read(): array
    {
        $entries = $this->library->list();
        $names = [];
        foreach ($entries as $entry) {
            $names[$entry->getTemplate()->getCode()] = $this->translated($entry->getTemplate()->getName());
        }
        $groups = [];
        foreach ($this->templates->groups() as $code => $label) {
            $groups[] = ['code' => $code, 'label' => $this->translated($label)];
        }

        return [
            'groups' => $groups,
            'recommended' => array_values(array_filter(
                $this->library->recommended(),
                static fn (string $code): bool => isset($names[$code])
            )),
            'templates' => array_map(
                fn (LibraryEntryInterface $entry): array => $this->template($entry, $names),
                $entries
            ),
        ];
    }

    /**
     * One template as the page shows it
     *
     * @param LibraryEntryInterface $entry
     * @param array<string,string> $names the translated names of the templates by code
     * @return Template
     */
    private function template(LibraryEntryInterface $entry, array $names): array
    {
        $template = $entry->getTemplate();
        $inShop = array_flip($entry->getBlocksInShop());

        return [
            'code' => $template->getCode(),
            'version' => $template->getVersion(),
            'group' => $template->getGroup(),
            'name' => $names[$template->getCode()] ?? $template->getName(),
            'type' => $template->getType(),
            'icon' => $template->getIcon(),
            'description' => $this->translated($template->getDescription()),
            'summary' => $this->translated($template->getSummary()),
            'where' => $this->translated($template->getWhere()),
            'isBlock' => $template->isBlock(),
            'google' => [
                'status' => $template->getGoogleStatus()->value,
                'short' => $this->translated($template->getGoogleShort()),
                'long' => $this->translated($template->getGoogleLong()),
            ],
            'properties' => $template->getProperties(),
            'uses' => array_map(
                static fn (string $use): array => [
                    'code' => $use,
                    'name' => $names[$use] ?? $use,
                    'inShop' => isset($inShop[$use]),
                ],
                $template->getUses()
            ),
            'questions' => array_map(
                fn (LibraryQuestionInterface $question): array => [
                    'code' => $question->getCode(),
                    'label' => $this->translated($question->getLabel()),
                ],
                $template->getQuestions()
            ),
            'added' => $entry->isAdded(),
            'hasNewerVersion' => $entry->hasNewerVersion(),
            'nextName' => $entry->getNextName(),
        ];
    }

    /**
     * A library text in the admin's locale; an empty text stays empty
     *
     * @param string $text
     * @return string
     */
    private function translated(string $text): string
    {
        return $text === '' ? '' : (string) __($text);
    }
}
