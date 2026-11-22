<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Page;

use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Api\PageKindResolverInterface;

/**
 * The kinds of page listed in the container configuration, by code
 *
 * A full action name belongs to the one kind that lists it; a request whose full action name no kind lists is a
 * page of the fallback kind. Two kinds listing the same full action name, a fallback kind missing from the list, or
 * an item that is not a kind of page are configuration errors, refused when the pool is built rather than resolved
 * one way or the other at random.
 */
class PageKindPool implements PageKindPoolInterface
{
    /**
     * @var array<string, PageKindResolverInterface>
     */
    private array $kinds = [];

    /**
     * @var array<string, string> kind codes by full action name
     */
    private array $kindByAction = [];

    /**
     * @param array<array-key,mixed> $kinds the kinds by code
     * @param string $fallbackKind the kind of a request no kind owns
     * @throws \InvalidArgumentException
     */
    public function __construct(array $kinds, private readonly string $fallbackKind)
    {
        foreach ($kinds as $code => $kind) {
            if (!$kind instanceof PageKindResolverInterface) {
                throw new \InvalidArgumentException(sprintf('The page kind "%s" is not a kind of page.', $code));
            }
            $this->add((string) $code, $kind);
        }
        if (!isset($this->kinds[$fallbackKind])) {
            throw new \InvalidArgumentException(
                sprintf('The fallback page kind "%s" is not among the kinds of page.', $fallbackKind)
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getCodes(): array
    {
        return array_keys($this->kinds);
    }

    /**
     * @inheritDoc
     */
    public function has(string $kind): bool
    {
        return isset($this->kinds[$kind]);
    }

    /**
     * @inheritDoc
     */
    public function get(string $kind): PageKindResolverInterface
    {
        return $this->kinds[$kind]
            ?? throw new \InvalidArgumentException(sprintf('No kind of page has the code "%s".', $kind));
    }

    /**
     * @inheritDoc
     */
    public function kindOfAction(string $fullActionName): string
    {
        return $this->kindByAction[$fullActionName] ?? $this->fallbackKind;
    }

    /**
     * Register a kind and the full action names it owns
     *
     * @param string $code
     * @param PageKindResolverInterface $kind
     * @return void
     * @throws \InvalidArgumentException when another kind owns one of its full action names
     */
    private function add(string $code, PageKindResolverInterface $kind): void
    {
        $this->kinds[$code] = $kind;
        foreach ($kind->getActionNames() as $action) {
            $owner = $this->kindByAction[$action] ?? null;
            if ($owner !== null && $owner !== $code) {
                throw new \InvalidArgumentException(sprintf(
                    'The full action name "%s" belongs to the page kinds "%s" and "%s"; it may belong to one.',
                    $action,
                    $owner,
                    $code
                ));
            }
            $this->kindByAction[$action] = $code;
        }
    }
}
