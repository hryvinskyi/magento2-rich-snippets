<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Condition;

use Hryvinskyi\RichSnippets\Api\ConditionFieldInterface;
use Hryvinskyi\RichSnippets\Api\ConditionFieldPoolInterface;
use Hryvinskyi\RichSnippets\Api\PageKindPoolInterface;

/**
 * The condition fields listed in the container configuration, by code
 *
 * An item that is not a condition field, or a field offered for a kind of page that does not exist, is a
 * configuration error, refused when the pool is built.
 */
class ConditionFieldPool implements ConditionFieldPoolInterface
{
    /**
     * @var array<string, ConditionFieldInterface>
     */
    private array $fields = [];

    /**
     * @param PageKindPoolInterface $kinds
     * @param array<array-key,mixed> $fields the condition fields by code
     * @throws \InvalidArgumentException
     */
    public function __construct(PageKindPoolInterface $kinds, array $fields = [])
    {
        foreach ($fields as $code => $field) {
            if (!$field instanceof ConditionFieldInterface) {
                throw new \InvalidArgumentException(
                    sprintf('The condition field "%s" is not a condition field.', $code)
                );
            }
            if (!$kinds->has($field->getPageKind())) {
                throw new \InvalidArgumentException(sprintf(
                    'The condition field "%s" is offered for the kind of page "%s", which does not exist.',
                    $code,
                    $field->getPageKind()
                ));
            }
            $this->fields[(string) $code] = $field;
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $code): bool
    {
        return isset($this->fields[$code]);
    }

    /**
     * @inheritDoc
     */
    public function get(string $code): ConditionFieldInterface
    {
        return $this->fields[$code]
            ?? throw new \InvalidArgumentException(sprintf('No condition field has the code "%s".', $code));
    }

    /**
     * @inheritDoc
     */
    public function getCodes(): array
    {
        return array_map('strval', array_keys($this->fields));
    }

    /**
     * @inheritDoc
     */
    public function forKind(string $kind): array
    {
        return array_filter(
            $this->fields,
            static fn (ConditionFieldInterface $field): bool => $field->getPageKind() === $kind
        );
    }
}
