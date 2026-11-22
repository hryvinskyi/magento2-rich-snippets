<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Snippet\Factory;

use Hryvinskyi\RichSnippets\Api\Data\OriginInterface;
use Hryvinskyi\RichSnippets\Api\Data\OriginInterfaceFactory;
use Hryvinskyi\RichSnippets\Api\OriginFactoryInterface;

/**
 * Delegates to the generated factory of the origin contract
 */
class OriginFactoryAdapter implements OriginFactoryInterface
{
    /**
     * @param OriginInterfaceFactory $generated
     */
    public function __construct(private readonly OriginInterfaceFactory $generated)
    {
    }

    /**
     * @inheritDoc
     */
    public function create(string $code, int $version): OriginInterface
    {
        return $this->generated->create(['code' => $code, 'version' => $version]);
    }
}
