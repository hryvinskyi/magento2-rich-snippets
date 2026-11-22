<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\Data\PageMatchInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageTestResultInterface;
use Hryvinskyi\RichSnippets\Api\PageSearchInterface;
use Hryvinskyi\RichSnippets\Api\PageTestInterface;
use Hryvinskyi\RichSnippets\Api\StorefrontPathInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorPresenter;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorRequest;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsRequest;

/**
 * Answers the Test page's "One page" mode: the page picker's search and the test of the page picked
 *
 * The search, `{"query", "store"}`, answers `{"pages": [page as the sample picker lists it], "address"}`: the
 * pages of the kinds listed in the container configuration whose name holds the text, and, when the text is a
 * storefront path, that path as "any address" (null otherwise). The test takes `{"page": "kind:id"}` (the home page
 * may be `home`) or `{"path": "/checkout/cart"}`, with `store`, and answers as PageTestPresenter writes it. A page
 * the store view does not have, or a text that is not a storefront path, is refused (answered 400). An address with
 * a host is never taken: only a path is.
 *
 * @phpstan-import-type Sample from EditorPresenter
 * @phpstan-import-type Answer from PageTestPresenter
 */
class TestAnswers
{
    public const PAGE = 'page';
    public const PATH = 'path';

    /**
     * @param EditorRequest $request
     * @param EditorPresenter $editor
     * @param PageSearchInterface $pageSearch
     * @param StorefrontPathInterface $paths
     * @param PageTestInterface $pageTest
     * @param PageTestPresenter $presenter
     * @param list<string> $searchedKinds the kinds of page the picker finds, in the order it lists them
     * @param int $limit the most pages of each kind the picker lists
     */
    public function __construct(
        private readonly EditorRequest $request,
        private readonly EditorPresenter $editor,
        private readonly PageSearchInterface $pageSearch,
        private readonly StorefrontPathInterface $paths,
        private readonly PageTestInterface $pageTest,
        private readonly PageTestPresenter $presenter,
        private readonly array $searchedKinds = ['home', 'product', 'category', 'cms'],
        private readonly int $limit = 8
    ) {
    }

    /**
     * The pages the picker offers for a text
     *
     * @param array<mixed> $query `{"query", "store"}`
     * @return array{pages: list<Sample>, address: string|null}
     * @throws InvalidWizardRequestException
     */
    public function search(array $query): array
    {
        $text = $this->request->query($query);
        $found = $this->pageSearch->search($text, $this->request->storeId($query), $this->searchedKinds, $this->limit);

        return [
            'pages' => array_map(fn (PageMatchInterface $match): array => $this->editor->sample($match), $found),
            'address' => $this->address($text),
        ];
    }

    /**
     * Test the page the body names
     *
     * @param array<mixed> $body `{"page" | "path", "store"}`
     * @return Answer
     * @throws InvalidWizardRequestException
     */
    public function run(array $body): array
    {
        $page = $body[self::PAGE] ?? null;
        $path = $body[self::PATH] ?? null;
        if (($page === null) === ($path === null)) {
            throw new InvalidWizardRequestException(self::PAGE, 'The request names neither one page nor one path.');
        }
        if ($path !== null) {
            return $this->presenter->present($this->testPath($path, $this->request->storeId($body)));
        }
        $entity = $this->request->sample([
            EditorRequest::SAMPLE => $page,
            SnippetsRequest::STORE => $body[SnippetsRequest::STORE] ?? null,
        ]);
        try {
            $result = $this->pageTest->testPage($entity);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidWizardRequestException(self::PAGE, 'The store view has no such page.', $e);
        }

        return $this->presenter->present($result);
    }

    /**
     * Test the page at a path
     *
     * @param mixed $path
     * @param int $storeId
     * @return PageTestResultInterface
     * @throws InvalidWizardRequestException
     */
    private function testPath(mixed $path, int $storeId): PageTestResultInterface
    {
        if (!is_string($path)) {
            throw new InvalidWizardRequestException(self::PATH, 'The path is not text.');
        }
        try {
            return $this->pageTest->testPath($path, $storeId);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidWizardRequestException(self::PATH, 'The path is not a storefront path.', $e);
        }
    }

    /**
     * The text as a storefront path; null when it is not one
     *
     * @param string $text
     * @return string|null
     */
    private function address(string $text): ?string
    {
        if (!str_starts_with($text, '/')) {
            return null;
        }
        try {
            return $this->paths->normalize($text);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
