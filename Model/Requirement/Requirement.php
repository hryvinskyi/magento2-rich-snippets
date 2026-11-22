<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement;

use Hryvinskyi\RichSnippets\Api\Data\RequirementInterface;
use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;

/**
 * A requirement of a rule: one property path, or several any one of which will do
 *
 * A path is property names joined by dots; a name starts with a letter (an `@` may come first) and holds letters,
 * digits and `_`. A requirement with no path, or with a path of another shape, cannot exist.
 */
class Requirement implements RequirementInterface
{
    public const PATH_PATTERN = '/\A@?[A-Za-z][A-Za-z0-9_]*(?:\.@?[A-Za-z][A-Za-z0-9_]*)*\z/';

    /**
     * @var non-empty-list<string>
     */
    private readonly array $paths;

    /**
     * @param list<string> $paths the first one stands for the group
     * @param string $missingTitle the title shown when the requirement is missing; empty for none
     * @param string $why why the property is worth adding; empty for none
     * @throws MalformedRuleException when there is no path, a path repeats or one has another shape, or the title
     *     or the reason is only white space or holds a control character
     */
    public function __construct(
        array $paths,
        private readonly string $missingTitle = '',
        private readonly string $why = ''
    ) {
        if ($paths === []) {
            throw new MalformedRuleException('A requirement needs at least one property path.');
        }
        foreach ($paths as $path) {
            if (preg_match(self::PATH_PATTERN, $path) !== 1) {
                throw new MalformedRuleException(sprintf('"%s" is not a property path.', $path));
            }
        }
        if (count(array_unique($paths)) !== count($paths)) {
            throw new MalformedRuleException('A requirement names a property path twice.');
        }
        foreach ([$missingTitle, $why] as $phrase) {
            if ($phrase !== '' && (trim($phrase) === '' || preg_match('/[\x00-\x1F\x7F]/', $phrase) === 1)) {
                throw new MalformedRuleException('A requirement\'s phrase is blank or holds a control character.');
            }
        }
        $this->paths = $paths;
    }

    /**
     * @inheritDoc
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->paths[0];
    }

    /**
     * @inheritDoc
     */
    public function isAnyOf(): bool
    {
        return count($this->paths) > 1;
    }

    /**
     * @inheritDoc
     */
    public function getMissingTitle(): string
    {
        return $this->missingTitle;
    }

    /**
     * @inheritDoc
     */
    public function getWhy(): string
    {
        return $this->why;
    }
}
