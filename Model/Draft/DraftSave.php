<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Draft;

use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\DraftSaveResultInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\SnippetDraftSaveInterface;
use Hryvinskyi\RichSnippets\Model\Idempotency\IdempotentWriteInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SaveSnippet;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootRepositoryInterface;

/**
 * Saves the editor's drafts through the save use case, which compiles the template, refuses a loop, stores the
 * snippet and cleans the pages it is on once the save commits
 *
 * The draft is checked first, so a draft that breaks a rule comes back with every problem rather than the first
 * refusal. A new draft is checked before its idempotency key is claimed, so a refused draft leaves the key unused;
 * the key's stored answer is the new snippet's ID.
 */
class DraftSave implements SnippetDraftSaveInterface
{
    private const ACTION_CREATE = 'snippet_create';
    private const RESULT_ID = 'id';

    /**
     * @param DraftRoots $roots
     * @param DraftValidation $validation
     * @param DraftProblems $problems
     * @param DraftFactoryInterface $factory
     * @param SaveSnippet $saveSnippet
     * @param SnippetRootRepositoryInterface $repository
     * @param IdempotentWriteInterface $idempotentWrite
     */
    public function __construct(
        private readonly DraftRoots $roots,
        private readonly DraftValidation $validation,
        private readonly DraftProblems $problems,
        private readonly DraftFactoryInterface $factory,
        private readonly SaveSnippet $saveSnippet,
        private readonly SnippetRootRepositoryInterface $repository,
        private readonly IdempotentWriteInterface $idempotentWrite
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(
        DraftSnippetInterface $draft,
        ?string $expectedUpdatedAt,
        ?string $idempotencyKey
    ): DraftSaveResultInterface {
        try {
            if ($draft->getId() !== null) {
                return $this->factory->saveResult($this->checkedSave($draft, $expectedUpdatedAt)());
            }
            if ($idempotencyKey === null) {
                return $this->factory->saveResult($this->checkedSave($draft, null)());
            }
            $created = $this->idempotentWrite->once(
                $idempotencyKey,
                self::ACTION_CREATE,
                function () use ($draft): callable {
                    $save = $this->checkedSave($draft, null);

                    return static fn (): array => [self::RESULT_ID => (int) $save()->getId()];
                }
            );
            $id = $created[self::RESULT_ID] ?? null;

            return $this->factory->saveResult($this->repository->get(is_numeric($id) ? (int) $id : 0));
        } catch (DraftRefusedException $e) {
            return $this->factory->saveResult(null, $e->getProblems());
        }
    }

    /**
     * Check a draft written into the snippet it is saved as, and return the save that stores it
     *
     * @param DraftSnippetInterface $draft
     * @param string|null $expectedUpdatedAt
     * @return callable():SnippetRootInterface
     * @throws DraftRefusedException when a problem stops the save
     */
    private function checkedSave(DraftSnippetInterface $draft, ?string $expectedUpdatedAt): callable
    {
        $snippet = $this->roots->toSave($draft);
        $blocking = $this->validation->blocking($this->validation->check($snippet, $draft));
        if ($blocking !== []) {
            throw new DraftRefusedException($blocking);
        }

        return function () use ($snippet, $expectedUpdatedAt): SnippetRootInterface {
            try {
                return $this->saveSnippet->save($snippet, $expectedUpdatedAt);
            } catch (SnippetRuleException $e) {
                throw new DraftRefusedException([$this->problems->fromRule($e)]);
            } catch (CompileException $e) {
                throw new DraftRefusedException($this->problems->fromCompile($e));
            }
        };
    }
}
