<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Test\Unit\Fake;

use Hryvinskyi\JsonTemplate\Api\Data\AstNodeKind;
use Hryvinskyi\RichSnippets\Model\Requirement\OutputPaths;
use Hryvinskyi\RichSnippets\Model\Requirement\RequirementChecker;
use Hryvinskyi\RichSnippets\Model\Requirement\Shape\ChildShape;
use Hryvinskyi\RichSnippets\Model\Requirement\Shape\LiteralShape;
use Hryvinskyi\RichSnippets\Model\Requirement\Shape\ObjectShape;
use Hryvinskyi\RichSnippets\Model\Requirement\Shape\OpenShape;
use Hryvinskyi\RichSnippets\Model\Requirement\Shape\TextShape;
use Hryvinskyi\RichSnippets\Model\Requirement\TemplateShape;
use Hryvinskyi\RichSnippets\Model\Requirement\TraceReading;

/**
 * The requirement checker over the module's rule files and the template readers di.xml configures
 */
class ModuleChecker
{
    /**
     * A checker built as the container builds it
     *
     * @return RequirementChecker
     */
    public static function create(): RequirementChecker
    {
        $open = new OpenShape();
        $shape = new TemplateShape($open, [
            AstNodeKind::LITERAL => new LiteralShape(),
            AstNodeKind::TEXT => new TextShape(),
            AstNodeKind::OBJECT => new ObjectShape(),
            AstNodeKind::LIST => new ChildShape('items', true),
            AstNodeKind::DIRECTIVE => new ChildShape('body', false),
            AstNodeKind::INTERP => $open,
            AstNodeKind::INCLUDE => $open,
        ]);

        return new RequirementChecker(
            ModuleRules::pool(),
            new OutputPaths(),
            $shape,
            new TraceReading(),
            new CheckFactoryFake()
        );
    }
}
