<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionInterface;
use Hryvinskyi\RichSnippets\Model\Storefront\OutputPositionPool;

/**
 * The places the module's container configuration declares for the position setting, worded as it words them, each
 * written by a writer that prints nothing
 */
class ModulePositions
{
    /**
     * The pool, with any further places a test adds
     *
     * @param array<string, array{label: string, place: string, line: string, note: string}> $more
     * @return OutputPositionPool
     */
    public static function pool(array $more = []): OutputPositionPool
    {
        $silent = new class implements OutputPositionInterface {
            /**
             * @inheritDoc
             */
            public function write(array $blocks, bool $marked): string
            {
                return '';
            }
        };
        $positions = [
            'head' => [
                'label' => 'In the head',
                'place' => 'in the head',
                'line' => 'Each snippet that is on writes one JSON-LD script block into the head of the pages it is '
                    . 'set for.',
                'note' => 'One script block in the page head',
            ],
            'body_end' => [
                'label' => 'At the end of the body',
                'place' => 'at the end of the body',
                'line' => 'Each snippet that is on writes one JSON-LD script block at the end of the body of the pages '
                    . 'it is set for.',
                'note' => 'One script block at the end of the page body',
            ],
        ] + $more;

        return new OutputPositionPool(array_map(
            static fn (array $position): array => $position + ['writer' => $silent],
            $positions
        ));
    }
}
