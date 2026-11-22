<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Admin\Overview;

use Hryvinskyi\RichSnippets\Api\Data\AttentionItemInterface;

/**
 * Writes the items of the Overview's "Needs attention" list in the admin's words
 *
 * An item is `{"key", "tone", "title", "description", "action", "target"}`: `tone` is `bad`, `warn` or `info`;
 * `target` is the screen that fixes it, `{"route", "params"}`, from which the page builds the address.
 *
 * @phpstan-import-type Target from ScreenLinks
 * @phpstan-type Item array{key: string, tone: string, title: string, description: string, action: string,
 *     target: Target}
 */
class AttentionRows
{
    /**
     * @param ScreenLinks $links
     */
    public function __construct(private readonly ScreenLinks $links)
    {
    }

    /**
     * The items
     *
     * @param list<AttentionItemInterface> $items
     * @return list<Item>
     */
    public function rows(array $items): array
    {
        return array_map(fn (AttentionItemInterface $item): array => [
            'key' => $item->getKey(),
            'tone' => $item->getTone()->value,
            'title' => (string) __($item->getTitle(), ...$item->getTitleArguments()),
            'description' => (string) __($item->getDescription(), ...$item->getDescriptionArguments()),
            'action' => (string) __($item->getActionLabel()),
            'target' => $this->links->target($item->getTarget()->getRoute(), $item->getTarget()->getParams()),
        ], $items);
    }
}
