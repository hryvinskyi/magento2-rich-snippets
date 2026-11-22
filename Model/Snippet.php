<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model;

use Hryvinskyi\RichSnippets\Api\Data\ConditionTreeInterface;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Data\SnippetInterface;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleException;
use Hryvinskyi\RichSnippets\Api\Exception\SnippetRuleReason;
use Hryvinskyi\RichSnippets\Api\PlacementVocabularyInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\ExistingStoreViewsInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\SnippetRootInterface;
use Hryvinskyi\RichSnippets\Model\Snippet\TemplateTypeInterface;

/**
 * A snippet and its rules: the aggregate every change of a snippet goes through
 *
 * The constructor refuses a state the rules forbid, so a snippet restored from storage or handed in by a caller is
 * valid or never exists: the code is in the code grammar, the name is a single line of 1 to 255 characters, a
 * building block is switched off with no kinds of page or conditions, no store ID is negative, and the sort order is
 * not negative. A snippet set for no store view is a stored one, switched off, and the constructor accepts that
 * state for any stored snippet, since it is what storage holds once every store view it was set for is deleted; a new
 * snippet (no ID yet) is refused without a store view, and a stored one can be switched on again only once it is
 * placed in a store view. Whether a stored snippet may be changed to that state is the rule of
 * assertKeepsStoreViewsOf: only the deletion of its store views may do it. The methods keep the rules for every
 * change after that.
 */
class Snippet implements SnippetRootInterface
{
    private const NAME_MAX_LENGTH = 255;
    private const CONTROL_CHARACTER = '/[\x00-\x1F\x7F]/u';

    /**
     * @var string
     */
    private string $name;

    /**
     * @var list<string>
     */
    private array $pageKinds;

    /**
     * @var list<int>
     */
    private array $storeIds;

    /**
     * @param int|null $id
     * @param string $code
     * @param string $name
     * @param string $template
     * @param string $type
     * @param bool $isBlock
     * @param bool $active
     * @param list<string> $pageKinds
     * @param ConditionTreeInterface $conditions
     * @param list<int> $storeIds
     * @param OriginInterface|null $origin
     * @param int $sortOrder
     * @param string|null $updatedAt
     * @param string $updatedBy
     * @throws SnippetRuleException
     */
    public function __construct(
        private readonly ?int $id,
        private readonly string $code,
        string $name,
        private string $template,
        private string $type,
        private bool $isBlock,
        private bool $active,
        array $pageKinds,
        private ConditionTreeInterface $conditions,
        array $storeIds,
        private ?OriginInterface $origin,
        private int $sortOrder,
        private readonly ?string $updatedAt,
        private readonly string $updatedBy
    ) {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new SnippetRuleException(SnippetRuleReason::CodeInvalid, ['code' => $code]);
        }
        $this->name = $this->validName($name);
        $this->pageKinds = $this->distinctKinds($pageKinds);
        $this->storeIds = $storeIds === [] && !$active && $id !== null ? [] : $this->validStoreIds($storeIds);
        $this->assertSortOrder($sortOrder);
        if ($isBlock && $active) {
            throw new SnippetRuleException(SnippetRuleReason::BlockCannotBeSwitchedOn);
        }
        if ($isBlock && ($this->pageKinds !== [] || !$conditions->isEmpty())) {
            throw new SnippetRuleException(SnippetRuleReason::BlockHasNoPlacement);
        }
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function isBlock(): bool
    {
        return $this->isBlock;
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @inheritDoc
     */
    public function getPageKinds(): array
    {
        return $this->pageKinds;
    }

    /**
     * @inheritDoc
     */
    public function getConditions(): ConditionTreeInterface
    {
        return $this->conditions;
    }

    /**
     * @inheritDoc
     */
    public function getStoreIds(): array
    {
        return $this->storeIds;
    }

    /**
     * @inheritDoc
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @inheritDoc
     */
    public function getOrigin(): ?OriginInterface
    {
        return $this->origin;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedBy(): string
    {
        return $this->updatedBy;
    }

    /**
     * @inheritDoc
     */
    public function rename(string $name): void
    {
        $this->name = $this->validName($name);
    }

    /**
     * @inheritDoc
     */
    public function changeTemplate(string $template, TemplateTypeInterface $types): void
    {
        $this->template = $template;
        $this->type = $types->typesOf($template)[0] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function retype(string $type, TemplateTypeInterface $types): void
    {
        $this->assertType($type);
        $this->template = $types->withType($this->template, $type);
        $this->type = $type;
    }

    /**
     * @inheritDoc
     */
    public function projectType(TemplateTypeInterface $types): void
    {
        $all = $types->typesOf($this->template);
        if ($all === []) {
            throw new SnippetRuleException(SnippetRuleReason::TypeMissing);
        }
        foreach ($all as $type) {
            $this->assertType($type);
        }
        $this->type = $all[0];
    }

    /**
     * @inheritDoc
     */
    public function place(
        array $pageKinds,
        ConditionTreeInterface $conditions,
        array $storeIds,
        PlacementVocabularyInterface $vocabulary
    ): void {
        if ($this->isBlock) {
            throw new SnippetRuleException(SnippetRuleReason::BlockHasNoPlacement);
        }
        $kinds = $this->distinctKinds($pageKinds);
        foreach ($kinds as $kind) {
            if (!$vocabulary->hasPageKind($kind)) {
                throw new SnippetRuleException(SnippetRuleReason::PageKindUnknown, ['kind' => $kind]);
            }
        }
        foreach ($conditions->getLeaves() as $condition) {
            $field = $condition->getField();
            $kind = $vocabulary->pageKindOfField($field);
            if ($kind === null) {
                throw new SnippetRuleException(SnippetRuleReason::ConditionFieldUnknown, ['field' => $field]);
            }
            if (!in_array($kind, $kinds, true)) {
                throw new SnippetRuleException(
                    SnippetRuleReason::ConditionFieldOutsideKinds,
                    ['field' => $field, 'kind' => $kind]
                );
            }
        }
        $stores = $storeIds === [] && $this->storeIds === [] ? [] : $this->validStoreIds($storeIds);

        $this->pageKinds = $kinds;
        $this->conditions = $conditions;
        $this->storeIds = $stores;
    }

    /**
     * @inheritDoc
     */
    public function switchOn(): void
    {
        if ($this->isBlock) {
            throw new SnippetRuleException(SnippetRuleReason::BlockCannotBeSwitchedOn);
        }
        if ($this->storeIds === []) {
            throw new SnippetRuleException(SnippetRuleReason::StoresMissing);
        }
        $this->active = true;
    }

    /**
     * @inheritDoc
     */
    public function leaveStoreView(int $storeId): void
    {
        if (!in_array($storeId, $this->storeIds, true) || $storeId === self::ALL_STORES) {
            return;
        }
        $this->storeIds = array_values(array_diff($this->storeIds, [$storeId]));
        if ($this->storeIds === []) {
            $this->active = false;
        }
    }

    /**
     * @inheritDoc
     */
    public function assertKeepsStoreViewsOf(SnippetInterface $stored, ExistingStoreViewsInterface $storeViews): void
    {
        if ($this->storeIds !== [] || $stored->getStoreIds() === []) {
            return;
        }
        if ($storeViews->among($stored->getStoreIds()) !== []) {
            throw new SnippetRuleException(SnippetRuleReason::StoresMissing);
        }
    }

    /**
     * @inheritDoc
     */
    public function switchOff(): void
    {
        $this->active = false;
    }

    /**
     * @inheritDoc
     */
    public function turnIntoBlock(ConditionTreeInterface $noConditions): void
    {
        if (!$noConditions->isEmpty()) {
            throw new SnippetRuleException(SnippetRuleReason::BlockHasNoPlacement);
        }
        $this->isBlock = true;
        $this->active = false;
        $this->pageKinds = [];
        $this->conditions = $noConditions;
        $this->storeIds = [self::ALL_STORES];
    }

    /**
     * @inheritDoc
     */
    public function turnIntoPageSnippet(): void
    {
        $this->isBlock = false;
        $this->active = false;
    }

    /**
     * @inheritDoc
     */
    public function moveTo(int $sortOrder): void
    {
        $this->assertSortOrder($sortOrder);
        $this->sortOrder = $sortOrder;
    }

    /**
     * @inheritDoc
     */
    public function recordOrigin(?OriginInterface $origin): void
    {
        $this->origin = $origin;
    }

    /**
     * The name without surrounding whitespace, refused when it is not a single line of 1 to 255 characters
     *
     * @param string $name
     * @return string
     * @throws SnippetRuleException
     */
    private function validName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === ''
            || preg_match(self::CONTROL_CHARACTER, $trimmed) !== 0
            || mb_strlen($trimmed, 'UTF-8') > self::NAME_MAX_LENGTH
        ) {
            throw new SnippetRuleException(SnippetRuleReason::NameInvalid);
        }

        return $trimmed;
    }

    /**
     * Refuse a type outside the schema.org type grammar
     *
     * @param string $type
     * @return void
     * @throws SnippetRuleException
     */
    private function assertType(string $type): void
    {
        if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
            throw new SnippetRuleException(SnippetRuleReason::TypeInvalid, ['type' => $type]);
        }
    }

    /**
     * The kinds in their first order, each once
     *
     * @param list<string> $kinds
     * @return list<string>
     * @throws SnippetRuleException when a kind is empty
     */
    private function distinctKinds(array $kinds): array
    {
        $distinct = [];
        foreach ($kinds as $kind) {
            if ($kind === '') {
                throw new SnippetRuleException(SnippetRuleReason::PageKindUnknown, ['kind' => $kind]);
            }
            $distinct[$kind] = $kind;
        }

        return array_values($distinct);
    }

    /**
     * The store views in ascending order, each once; `[0]` when every store view is among them
     *
     * @param list<int> $storeIds
     * @return list<int>
     * @throws SnippetRuleException when none is listed or one is negative
     */
    private function validStoreIds(array $storeIds): array
    {
        if ($storeIds === []) {
            throw new SnippetRuleException(SnippetRuleReason::StoresMissing);
        }
        foreach ($storeIds as $storeId) {
            if ($storeId < 0) {
                throw new SnippetRuleException(SnippetRuleReason::StoreInvalid, ['store' => (string) $storeId]);
            }
            if ($storeId === self::ALL_STORES) {
                return [self::ALL_STORES];
            }
        }
        $distinct = array_values(array_unique($storeIds));
        sort($distinct);

        return $distinct;
    }

    /**
     * Refuse a negative sort order
     *
     * @param int $sortOrder
     * @return void
     * @throws SnippetRuleException
     */
    private function assertSortOrder(int $sortOrder): void
    {
        if ($sortOrder < 0) {
            throw new SnippetRuleException(SnippetRuleReason::SortOrderInvalid);
        }
    }
}
