<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Snippets;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetInUseException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\SnippetManagementInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Runs the Snippets page's writes on one snippet and answers each the same way
 *
 * The answer is the list after the write, for the store view the body names (see SnippetRowsPresenter), with the ID
 * of the snippet written (null after a refusal and for a delete) and the refusal, if any: `{..., "id", "refused"}`.
 * A write the snippet rules refuse is part of the answer, not a failure: `refused` is `{"reason", "params",
 * "includers"}` — the reason a snippet rule's reason, `in_use` for a delete of an included snippet (with
 * `includers`, `[{"code", "name"}]`, the snippets that include it), `unknown_snippet` for a snippet that is not
 * stored, `template_invalid` for a template that no longer compiles. A body of the wrong shape, and an idempotency
 * key that is invalid or was used for another write, answer 400.
 *
 * @phpstan-import-type Listing from SnippetRowsPresenter
 * @phpstan-type Refusal array{reason: string, params: array<string, string>,
 *     includers: list<array{code: string, name: string}>}
 * @phpstan-type Answer array{rows: list<mixed>, counts: array<string, mixed>, id: int|null, refused: Refusal|null}
 */
class SnippetWrites
{
    public const IN_USE = 'in_use';
    public const UNKNOWN_SNIPPET = 'unknown_snippet';
    public const TEMPLATE_INVALID = 'template_invalid';

    /**
     * @param SnippetsRequest $request
     * @param SnippetManagementInterface $management
     * @param SnippetsListing $listing
     */
    public function __construct(
        private readonly SnippetsRequest $request,
        private readonly SnippetManagementInterface $management,
        private readonly SnippetsListing $listing
    ) {
    }

    /**
     * Switch a snippet on or off: `{"id", "active", "store"}`
     *
     * @param array<mixed> $body
     * @return Answer
     * @throws InvalidWizardRequestException
     */
    public function setActive(array $body): array
    {
        $id = $this->request->id($body);
        $active = $this->request->active($body);

        return $this->answer(
            $body,
            fn (): ?int => $this->management->setActive($id, $active)->getId()
        );
    }

    /**
     * Copy a snippet: `{"id", "idempotencyKey", "store"}`
     *
     * @param array<mixed> $body
     * @return Answer
     * @throws InvalidWizardRequestException
     */
    public function duplicate(array $body): array
    {
        $id = $this->request->id($body);
        $key = $this->request->idempotencyKey($body);

        return $this->answer(
            $body,
            fn (): ?int => $this->management->duplicate($id, $key)->getId()
        );
    }

    /**
     * Delete a snippet: `{"id", "store"}`
     *
     * @param array<mixed> $body
     * @return Answer
     * @throws InvalidWizardRequestException
     */
    public function delete(array $body): array
    {
        $id = $this->request->id($body);

        return $this->answer($body, function () use ($id): ?int {
            $this->management->delete($id);

            return null;
        });
    }

    /**
     * Run a write and answer the list after it, with its refusal if it was refused
     *
     * @param array<mixed> $body
     * @param callable():(int|null) $write returns the ID of the snippet written
     * @return Answer
     * @throws InvalidWizardRequestException
     */
    private function answer(array $body, callable $write): array
    {
        $this->request->storeId($body);
        $id = null;
        $refused = null;
        try {
            $id = $write();
        } catch (IdempotencyException $e) {
            throw new InvalidWizardRequestException($e->getReason()->value, 'The idempotency key is refused.', $e);
        } catch (SnippetInUseException $e) {
            $refused = $this->refusal(self::IN_USE, [], $e->getIncluders());
        } catch (SnippetRuleException $e) {
            $refused = $this->refusal($e->getReason()->value, $e->getParams(), []);
        } catch (CompileException) {
            $refused = $this->refusal(self::TEMPLATE_INVALID, [], []);
        } catch (NoSuchEntityException) {
            $refused = $this->refusal(self::UNKNOWN_SNIPPET, [], []);
        }

        return $this->listing->of($body) + ['id' => $id, 'refused' => $refused];
    }

    /**
     * A refusal as the answer names it
     *
     * @param string $reason
     * @param array<string,string> $params
     * @param array<string,string> $includers names by code
     * @return Refusal
     */
    private function refusal(string $reason, array $params, array $includers): array
    {
        $listed = [];
        foreach ($includers as $code => $name) {
            $listed[] = ['code' => (string) $code, 'name' => $name];
        }

        return ['reason' => $reason, 'params' => $params, 'includers' => $listed];
    }
}
