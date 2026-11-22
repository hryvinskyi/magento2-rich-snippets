<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\StaleSnippetException;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Api\SnippetDraftSaveInterface;
use Hryvinskyi\RichSnippets\Api\SnippetRepositoryInterface;
use Hryvinskyi\RichSnippets\Model\Editor\IncludeSites;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeEdgesInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\IncludeGraph;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Loads a snippet into the editor and saves it back
 *
 * The load answer is `{"snippet", "usedIn", "vocabulary"}`: the snippet (null when no ID is asked for, for a new
 * one), the snippets that include it — each with its ID, code and name and the paths it includes it at — and the
 * condition fields each kind of page offers. The save answer is `{"snippet", "problems"}`: the snippet as stored, or
 * null and the problems that stopped the save. A save made from an older version than the stored one answers
 * `{"conflict": {"updatedAt", "updatedBy", "message"}}`, which the caller sends with the status 409. A snippet that
 * is not stored answers 400 `{"error": "id"}`; an idempotency key that is invalid or used for another write answers
 * 400 with its reason.
 *
 * @phpstan-import-type Document from EditorPresenter
 * @phpstan-import-type Problem from EditorPresenter
 * @phpstan-type UsedIn array{id: int|null, code: string, name: string, paths: list<string>}
 */
class EditorDocument
{
    /**
     * @param EditorRequest $request
     * @param EditorPresenter $presenter
     * @param SnippetRepositoryInterface $snippets
     * @param SnippetDraftSaveInterface $drafts
     * @param IncludeGraph $graph
     * @param IncludeEdgesInterface $edges
     * @param IncludeSites $sites
     * @param PageKindPoolInterface $kinds
     * @param ConditionFieldPoolInterface $conditionFields
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly EditorRequest $request,
        private readonly EditorPresenter $presenter,
        private readonly SnippetRepositoryInterface $snippets,
        private readonly SnippetDraftSaveInterface $drafts,
        private readonly IncludeGraph $graph,
        private readonly IncludeEdgesInterface $edges,
        private readonly IncludeSites $sites,
        private readonly PageKindPoolInterface $kinds,
        private readonly ConditionFieldPoolInterface $conditionFields,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * The snippet the query names, with what the editor shows around it
     *
     * @param array<mixed> $query
     * @return array{snippet: Document|null, usedIn: list<UsedIn>,
     *     vocabulary: array{conditions: array<string, list<array{code: string, label: string}>>}}
     * @throws InvalidWizardRequestException
     */
    public function load(array $query): array
    {
        $id = $this->request->optionalId($query);
        $snippet = $id === null ? null : $this->stored($id);

        return [
            'snippet' => $snippet === null ? null : $this->presenter->snippet($snippet),
            'usedIn' => $snippet === null ? [] : $this->usedIn($snippet),
            'vocabulary' => ['conditions' => $this->conditions()],
        ];
    }

    /**
     * Save the draft the body carries
     *
     * @param array<mixed> $body `{"snippet", "updatedAt", "idempotencyKey"}`; the key is needed for a new snippet
     * @return array{snippet: Document|null, problems: list<Problem>}
     * @throws InvalidWizardRequestException
     * @throws StaleSnippetException when the stored snippet was saved after the version the draft was made from
     */
    public function save(array $body): array
    {
        $draft = $this->request->draft($body);
        $key = $draft->getId() === null ? $this->request->idempotencyKey($body) : null;
        try {
            $result = $this->drafts->save($draft, $this->request->updatedAt($body), $key);
        } catch (NoSuchEntityException $e) {
            throw new InvalidWizardRequestException('id', 'The snippet is not stored.', $e);
        } catch (IdempotencyException $e) {
            throw new InvalidWizardRequestException($e->getReason()->value, 'The idempotency key is refused.', $e);
        }
        $snippet = $result->getSnippet();

        return [
            'snippet' => $snippet === null ? null : $this->presenter->snippet($snippet),
            'problems' => $this->presenter->problems($result->getProblems()),
        ];
    }

    /**
     * The answer to a save made from an older version than the stored one
     *
     * @param StaleSnippetException $stale
     * @return array{conflict: array{updatedAt: string, updatedBy: string, message: string}}
     */
    public function conflict(StaleSnippetException $stale): array
    {
        $time = $this->timezone->formatDateTime(
            $stale->getUpdatedAt(),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT
        );

        return ['conflict' => [
            'updatedAt' => $stale->getUpdatedAt(),
            'updatedBy' => $stale->getUpdatedBy(),
            'message' => (string) __(
                'Someone else saved this snippet at %1. Your changes are still here; reload to see theirs.',
                $time
            ),
        ]];
    }

    /**
     * A stored snippet
     *
     * @param int $id
     * @return SnippetInterface
     * @throws InvalidWizardRequestException when it is not stored
     */
    private function stored(int $id): SnippetInterface
    {
        try {
            return $this->snippets->get($id);
        } catch (NoSuchEntityException $e) {
            throw new InvalidWizardRequestException('id', 'The snippet is not stored.', $e);
        }
    }

    /**
     * The snippets that include a snippet, each with the paths it includes it at, by code
     *
     * @param SnippetInterface $snippet
     * @return list<UsedIn>
     */
    private function usedIn(SnippetInterface $snippet): array
    {
        $includers = array_flip($this->graph->directIncludersOf($snippet->getCode(), $this->edges->edges()));
        $used = [];
        foreach ($this->snippets->list() as $includer) {
            if (isset($includers[$includer->getCode()])) {
                $used[$includer->getCode()] = [
                    'id' => $includer->getId(),
                    'code' => $includer->getCode(),
                    'name' => $includer->getName(),
                    'paths' => $this->sites->of($includer->getTemplate(), $snippet->getCode()),
                ];
            }
        }
        ksort($used, SORT_STRING);

        return array_values($used);
    }

    /**
     * The condition fields each kind of page offers, in their configured order
     *
     * @return array<string, list<array{code: string, label: string}>>
     */
    private function conditions(): array
    {
        $conditions = [];
        foreach ($this->kinds->getCodes() as $kind) {
            $conditions[$kind] = [];
            foreach ($this->conditionFields->forKind($kind) as $code => $field) {
                $conditions[$kind][] = ['code' => (string) $code, 'label' => $field->getLabel()];
            }
        }

        return $conditions;
    }
}
