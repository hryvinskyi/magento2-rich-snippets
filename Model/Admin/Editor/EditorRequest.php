<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Editor;

use Hryvinskyi\AdminSetupWizard\Api\Exception\InvalidWizardRequestException;
use Hryvinskyi\RichSnippets\Api\ConditionTreeParserInterface;
use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\DraftSnippetInterface;
use Hryvinskyi\RichSnippets\Api\Data\PageEntityInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\PageEntityFactoryInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;
use Hryvinskyi\RichSnippets\Model\Admin\Snippets\SnippetsRequest;
use Hryvinskyi\RichSnippets\Model\Draft\DraftFactoryInterface;
use Hryvinskyi\RichSnippets\Model\Page\Cms\StoreCmsPagesInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reads what the editor's requests carry, refusing a value of the wrong shape with the name of the value as the
 * reason (answered 400)
 *
 * A draft is the body's `snippet` object: `{"id", "name", "template", "isBlock", "active", "pageKinds", "conditions",
 * "storeIds"}`, `id` left out or null for a new snippet, `conditions` the expression builder's text. A sample page is
 * `kind:id` (`product:12`); the home page may be written `home`. A store view is `store`, as on the Snippets page;
 * where a read needs one store view and none is named, the default store view is read.
 */
class EditorRequest
{
    public const SNIPPET = 'snippet';
    public const SAMPLE = 'sample';
    public const UPDATED_AT = 'updatedAt';
    public const FIELD = 'field';
    public const QUERY = 'query';
    public const KINDS = 'kinds';
    public const ATTRIBUTE = 'attribute';
    public const SUGGEST_QUERY = 'q';
    public const CONDITIONS = 'conditions';

    private const HOME = 'home';
    private const TIMESTAMP_PATTERN = '/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/';
    private const MAX_QUERY_LENGTH = 255;

    /**
     * @param SnippetsRequest $snippets the Snippets page's reader, for store views, IDs and idempotency keys
     * @param DraftFactoryInterface $drafts
     * @param ConditionTreeParserInterface $parser
     * @param PageEntityFactoryInterface $pages
     * @param PageKindPoolInterface $kinds
     * @param StoreManagerInterface $storeManager
     * @param StoreCmsPagesInterface $homePages
     */
    public function __construct(
        private readonly SnippetsRequest $snippets,
        private readonly DraftFactoryInterface $drafts,
        private readonly ConditionTreeParserInterface $parser,
        private readonly PageEntityFactoryInterface $pages,
        private readonly PageKindPoolInterface $kinds,
        private readonly StoreManagerInterface $storeManager,
        private readonly StoreCmsPagesInterface $homePages
    ) {
    }

    /**
     * The draft the body carries
     *
     * @param array<mixed> $values
     * @return DraftSnippetInterface
     * @throws InvalidWizardRequestException
     */
    public function draft(array $values): DraftSnippetInterface
    {
        $draft = $values[self::SNIPPET] ?? null;
        if (!is_array($draft) || array_is_list($draft)) {
            throw new InvalidWizardRequestException(self::SNIPPET, 'The request carries no snippet.');
        }
        $id = ($draft['id'] ?? null) === null ? null : $this->snippets->id($draft);

        return $this->drafts->draft(
            $id,
            $this->text($draft, 'name', ''),
            $this->text($draft, 'template', ''),
            $this->flag($draft, 'isBlock'),
            $this->flag($draft, 'active'),
            $this->texts($draft, 'pageKinds'),
            $this->text($draft, self::CONDITIONS, ''),
            $this->storeIds($draft)
        );
    }

    /**
     * The sample page, in the store view the values name or the default one
     *
     * @param array<mixed> $values
     * @return PageEntityInterface
     * @throws InvalidWizardRequestException when there is no sample, or it names no kind of page or no entity
     */
    public function sample(array $values): PageEntityInterface
    {
        $sample = $this->optionalSample($values);
        if ($sample === null) {
            throw new InvalidWizardRequestException(self::SAMPLE, 'The request names no sample page.');
        }

        return $sample;
    }

    /**
     * The sample page when the values name one
     *
     * @param array<mixed> $values
     * @return PageEntityInterface|null
     * @throws InvalidWizardRequestException when it names no kind of page or no entity
     */
    public function optionalSample(array $values): ?PageEntityInterface
    {
        $sample = $values[self::SAMPLE] ?? '';
        if ($sample === '') {
            return null;
        }
        if (!is_string($sample)) {
            throw new InvalidWizardRequestException(self::SAMPLE, 'The sample page is not text.');
        }
        [$kind, $id] = array_pad(explode(':', $sample, 2), 2, '');
        if (!$this->kinds->has($kind) || ($id !== '' && !ctype_digit($id)) || ($id === '' && $kind !== self::HOME)) {
            throw new InvalidWizardRequestException(self::SAMPLE, 'The sample names no page of the shop.');
        }
        $storeId = $this->storeId($values);
        $entityId = $id === '' ? null : (int) $id;
        if ($entityId === null) {
            $entityId = $this->homePageId($storeId);
        }
        if ($entityId === null || $entityId < 1) {
            throw new InvalidWizardRequestException(self::SAMPLE, 'The sample names no page of the shop.');
        }

        return $this->pages->create($kind, $entityId, $storeId);
    }

    /**
     * The one store view a read is for: the one the values name, else the default store view
     *
     * @param array<mixed> $values
     * @return int
     * @throws InvalidWizardRequestException when the values name no store view of the shop
     */
    public function storeId(array $values): int
    {
        return $this->snippets->storeId($values) ?? (int) $this->storeManager->getDefaultStoreView()?->getId();
    }

    /**
     * The snippet the request is about
     *
     * @param array<mixed> $values
     * @return int
     * @throws InvalidWizardRequestException
     */
    public function id(array $values): int
    {
        return $this->snippets->id($values);
    }

    /**
     * The snippet the request is about, when it names one
     *
     * @param array<mixed> $values
     * @return int|null
     * @throws InvalidWizardRequestException when it names one that is not a positive ID
     */
    public function optionalId(array $values): ?int
    {
        return ($values[SnippetsRequest::ID] ?? '') === '' ? null : $this->snippets->id($values);
    }

    /**
     * The update time of the version a save was made from; null when the body carries none
     *
     * @param array<mixed> $values
     * @return string|null
     * @throws InvalidWizardRequestException when it is not a database timestamp
     */
    public function updatedAt(array $values): ?string
    {
        $updatedAt = $values[self::UPDATED_AT] ?? null;
        if ($updatedAt === null) {
            return null;
        }
        if (!is_string($updatedAt) || preg_match(self::TIMESTAMP_PATTERN, $updatedAt) !== 1) {
            throw new InvalidWizardRequestException(self::UPDATED_AT, 'The update time is not a timestamp.');
        }

        return $updatedAt;
    }

    /**
     * The idempotency key of a save that creates a snippet
     *
     * @param array<mixed> $values
     * @return string
     * @throws InvalidWizardRequestException when there is none
     */
    public function idempotencyKey(array $values): string
    {
        return $this->snippets->idempotencyKey($values);
    }

    /**
     * The condition field asked about
     *
     * @param array<mixed> $values
     * @return string
     * @throws InvalidWizardRequestException when there is none
     */
    public function field(array $values): string
    {
        $field = $values[self::FIELD] ?? null;
        if (!is_string($field) || $field === '') {
            throw new InvalidWizardRequestException(self::FIELD, 'The request names no condition field.');
        }

        return $field;
    }

    /**
     * The text a search looks for; empty for any
     *
     * @param array<mixed> $values
     * @return string
     * @throws InvalidWizardRequestException when it is not text or is too long
     */
    public function query(array $values): string
    {
        $query = $values[self::QUERY] ?? '';
        if (!is_string($query) || mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            throw new InvalidWizardRequestException(self::QUERY, 'The search is not text of up to 255 characters.');
        }

        return trim($query);
    }

    /**
     * The kinds of page a read is about, a list or a comma-separated text; every kind when none is named
     *
     * @param array<mixed> $values
     * @return list<string>
     * @throws InvalidWizardRequestException when one names no kind of page
     */
    public function kinds(array $values): array
    {
        $given = $values[self::KINDS] ?? '';
        $kinds = is_string($given)
            ? array_filter(explode(',', $given), static fn (string $kind): bool => $kind !== '')
            : $given;
        if (!is_array($kinds)) {
            throw new InvalidWizardRequestException(self::KINDS, 'The kinds of page are not a list.');
        }
        $known = [];
        foreach ($kinds as $kind) {
            if (!is_string($kind) || !$this->kinds->has($kind)) {
                throw new InvalidWizardRequestException(self::KINDS, 'A kind of page is not one the shop has.');
            }
            $known[] = $kind;
        }

        return $known === [] ? $this->kinds->getCodes() : array_values(array_unique($known));
    }

    /**
     * An optional text parameter: the attribute a condition reads, a type-ahead's query; empty when left out
     *
     * @param array<mixed> $values
     * @param string $key
     * @return string
     * @throws InvalidWizardRequestException when it is not text or is too long
     */
    public function parameter(array $values, string $key): string
    {
        $value = $values[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > self::MAX_QUERY_LENGTH) {
            throw new InvalidWizardRequestException($key, sprintf('"%s" is not text of up to 255 characters.', $key));
        }

        return $value;
    }

    /**
     * The placement the body carries: kinds, conditions and store views
     *
     * It is read for counting the pages the placement reaches and for putting it into words.
     *
     * @param array<mixed> $values
     * @return array{kinds: list<string>, conditions: ConditionTreeInterface, storeIds: list<int>}
     * @throws InvalidWizardRequestException
     */
    public function placement(array $values): array
    {
        $kinds = $this->texts($values, 'pageKinds');
        foreach ($kinds as $kind) {
            if (!$this->kinds->has($kind)) {
                throw new InvalidWizardRequestException('pageKinds', 'A kind of page is not one the shop has.');
            }
        }

        return ['kinds' => $kinds, 'conditions' => $this->conditions($values), 'storeIds' => $this->storeIds($values)];
    }

    /**
     * The conditions the body carries, as the expression builder writes them; none when it carries none
     *
     * @param array<mixed> $values
     * @return ConditionTreeInterface
     * @throws InvalidWizardRequestException when they do not read
     */
    public function conditions(array $values): ConditionTreeInterface
    {
        try {
            return $this->parser->parse($this->text($values, self::CONDITIONS, ''));
        } catch (SnippetRuleException $e) {
            throw new InvalidWizardRequestException(self::CONDITIONS, 'The conditions do not read.', $e);
        }
    }

    /**
     * The store views of a draft; every store view when it names none
     *
     * @param array<mixed> $draft
     * @return list<int>
     * @throws InvalidWizardRequestException
     */
    private function storeIds(array $draft): array
    {
        $given = $draft['storeIds'] ?? [0];
        if (!is_array($given) || !array_is_list($given)) {
            throw new InvalidWizardRequestException(self::SNIPPET, 'The snippet\'s store views are not a list.');
        }
        $ids = [];
        foreach ($given as $id) {
            if (!is_int($id)) {
                throw new InvalidWizardRequestException(self::SNIPPET, 'A store view of the snippet is not an ID.');
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * A text value, with a default when it is left out
     *
     * @param array<mixed> $values
     * @param string $key
     * @param string $default
     * @return string
     * @throws InvalidWizardRequestException when it is there and not text
     */
    private function text(array $values, string $key, string $default): string
    {
        $value = $values[$key] ?? $default;
        if (!is_string($value)) {
            throw new InvalidWizardRequestException($key, sprintf('"%s" is not text.', $key));
        }

        return $value;
    }

    /**
     * A list of texts; empty when it is left out
     *
     * @param array<mixed> $values
     * @param string $key
     * @return list<string>
     * @throws InvalidWizardRequestException when it is there and not a list of texts
     */
    private function texts(array $values, string $key): array
    {
        $given = $values[$key] ?? [];
        if (!is_array($given) || !array_is_list($given)) {
            throw new InvalidWizardRequestException($key, sprintf('"%s" is not a list.', $key));
        }
        $texts = [];
        foreach ($given as $value) {
            if (!is_string($value)) {
                throw new InvalidWizardRequestException($key, sprintf('"%s" holds a value that is not text.', $key));
            }
            $texts[] = $value;
        }

        return $texts;
    }

    /**
     * A true or false value; false when it is left out
     *
     * @param array<mixed> $values
     * @param string $key
     * @return bool
     * @throws InvalidWizardRequestException when it is there and not true or false
     */
    private function flag(array $values, string $key): bool
    {
        $value = $values[$key] ?? false;
        if (!is_bool($value)) {
            throw new InvalidWizardRequestException($key, sprintf('"%s" is not true or false.', $key));
        }

        return $value;
    }

    /**
     * The CMS page a store view shows as its home page; null when it shows none
     *
     * @param int $storeId
     * @return int|null
     */
    private function homePageId(int $storeId): ?int
    {
        return $this->homePages->homePageId($storeId);
    }
}
