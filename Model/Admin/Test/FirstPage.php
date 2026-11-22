<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Test;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\PageSearchInterface;
use Hryvinskyi\RichSnippets\Api\StorefrontPathInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorPresenter;
use Hryvinskyi\RichSnippets\Model\Admin\Editor\EditorRequest;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsRequest;

/**
 * The page the Test page's "One page" mode tests first, as its page picker shows it
 *
 * The address names it as the page picker does (`?page=product:12`, `?page=home`) or by a storefront path
 * (`?path=/checkout/cart`), which wins when both are given. A page the store view does not have, or a text that is no
 * storefront path, falls back to the home page, as does an address that names none. A store view whose home page
 * cannot be found has no first page: the picker starts empty and nothing is tested until a page is picked.
 *
 * A page is `{"ref", "kind", "label", "path"}`: `ref` as the picker names it, null for a path; a path's kind is
 * `other` and its label the path itself.
 *
 * @phpstan-type Picked array{ref: string|null, kind: string, label: string, path: string}
 */
class FirstPage
{
    /** How the address names the home page */
    private const HOME = 'home';

    /** The kind of page a storefront path is tested as */
    private const ANY_ADDRESS = 'other';

    /**
     * @param EditorRequest $request
     * @param EditorPresenter $editor
     * @param PageSearchInterface $pageSearch
     * @param StorefrontPathInterface $paths
     */
    public function __construct(
        private readonly EditorRequest $request,
        private readonly EditorPresenter $editor,
        private readonly PageSearchInterface $pageSearch,
        private readonly StorefrontPathInterface $paths
    ) {
    }

    /**
     * The first page of a store view
     *
     * @param string $page how the address names the page; empty for none
     * @param string $path the storefront path the address names; empty for none
     * @param int $storeId
     * @return Picked|null
     */
    public function of(string $page, string $path, int $storeId): ?array
    {
        $address = $this->address($path);
        if ($address !== null) {
            return $address;
        }

        return ($page === '' ? null : $this->entity($page, $storeId)) ?? $this->entity(self::HOME, $storeId);
    }

    /**
     * A storefront path as the picker shows it; null when the text is none
     *
     * @param string $path
     * @return Picked|null
     */
    private function address(string $path): ?array
    {
        if ($path === '') {
            return null;
        }
        try {
            $normal = $this->paths->normalize($path);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return ['ref' => null, 'kind' => self::ANY_ADDRESS, 'label' => $normal, 'path' => $normal];
    }

    /**
     * A page of the store view as the picker shows it; null when the store view has no such page
     *
     * @param string $reference
     * @param int $storeId
     * @return Picked|null
     */
    private function entity(string $reference, int $storeId): ?array
    {
        try {
            $entity = $this->request->optionalSample([
                EditorRequest::SAMPLE => $reference,
                SnippetsRequest::STORE => (string) $storeId,
            ]);
        } catch (InvalidWizardRequestException) {
            return null;
        }
        $match = $entity === null ? null : $this->pageSearch->find($entity);
        if ($match === null) {
            return null;
        }
        $sample = $this->editor->sample($match);

        return [
            'ref' => $sample['ref'],
            'kind' => $sample['kind'],
            'label' => $sample['label'],
            'path' => $sample['path'],
        ];
    }
}
