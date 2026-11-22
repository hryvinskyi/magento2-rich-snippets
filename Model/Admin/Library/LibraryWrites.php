<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Library;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\JsonTemplate\Api\Exception\CompileException;
use Hryvinskyi\RichSnippets\Api\Data\AddedSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\IdempotencyException;
use Hryvinskyi\RichSnippets\Api\Exception\LibraryException;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedLibraryTemplateException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\LibraryInterface;

/**
 * Runs the Library page's two writes — add one template, add the recommended set — and answers each the same way
 *
 * The answer is `{"added": [{"id", "code", "name", "isBlock", "template", "reused"}], "refused", "library"}`: the
 * snippets the add ended with (for each template its building blocks first, then the copy; a block the shop had is
 * listed as reused), the refusal if the copy broke a snippet rule (`{"reason", "params"}`, the reason a snippet rule's,
 * or `template_invalid` for a template that does not compile), and the library after the write (see LibraryListing).
 * A replay of a key answers what the first request did. A body of the wrong shape, an unknown template, question or
 * answer, and an idempotency key that is invalid or was used for another write answer 400.
 *
 * @phpstan-import-type Listing from LibraryListing
 * @phpstan-type Added array{id: int, code: string, name: string, isBlock: bool, template: string, reused: bool}
 * @phpstan-type Refusal array{reason: string, params: array<string, string>}
 * @phpstan-type Answer array{added: list<Added>, refused: Refusal|null, library: Listing}
 */
class LibraryWrites
{
    public const TEMPLATE_INVALID = 'template_invalid';

    /**
     * @param LibraryRequest $request
     * @param LibraryInterface $library
     * @param LibraryListing $listing
     */
    public function __construct(
        private readonly LibraryRequest $request,
        private readonly LibraryInterface $library,
        private readonly LibraryListing $listing
    ) {
    }

    /**
     * Add a copy of a template with the answers chosen: `{"template", "answers", "idempotencyKey"}`
     *
     * @param array<mixed> $body
     * @return Answer
     * @throws InvalidWizardRequestException
     * @throws MalformedLibraryTemplateException
     */
    public function add(array $body): array
    {
        $code = $this->request->template($body);
        $answers = $this->request->answers($body);
        $key = $this->request->idempotencyKey($body);

        return $this->answer(fn (): array => $this->library->add($code, $answers, $key));
    }

    /**
     * Add the recommended templates the shop has no copy of: `{"idempotencyKey"}`
     *
     * @param array<mixed> $body
     * @return Answer
     * @throws InvalidWizardRequestException
     * @throws MalformedLibraryTemplateException
     */
    public function addRecommended(array $body): array
    {
        $key = $this->request->idempotencyKey($body);

        return $this->answer(fn (): array => $this->library->addRecommended($key));
    }

    /**
     * Run an add and answer what it ended with, or its refusal, with the library after it
     *
     * @param callable():list<AddedSnippetInterface> $add
     * @return Answer
     * @throws InvalidWizardRequestException
     * @throws MalformedLibraryTemplateException
     */
    private function answer(callable $add): array
    {
        $added = [];
        $refused = null;
        try {
            $added = $add();
        } catch (IdempotencyException $e) {
            throw new InvalidWizardRequestException($e->getReason()->value, 'The idempotency key is refused.', $e);
        } catch (LibraryException $e) {
            throw new InvalidWizardRequestException($e->getReason()->value, 'The library refused the request.', $e);
        } catch (SnippetRuleException $e) {
            $refused = ['reason' => $e->getReason()->value, 'params' => $e->getParams()];
        } catch (CompileException) {
            $refused = ['reason' => self::TEMPLATE_INVALID, 'params' => []];
        }

        return [
            'added' => array_map(
                static fn (AddedSnippetInterface $snippet): array => [
                    'id' => $snippet->getId(),
                    'code' => $snippet->getCode(),
                    'name' => $snippet->getName(),
                    'isBlock' => $snippet->isBlock(),
                    'template' => $snippet->getTemplate(),
                    'reused' => $snippet->isReused(),
                ],
                $added
            ),
            'refused' => $refused,
            'library' => $this->listing->read(),
        ];
    }
}
