<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\RichSnippets\Model\Requirement\Source;

use Hryvinskyi\RichSnippets\Api\Exception\MalformedRuleException;
use Hryvinskyi\RichSnippets\Api\ResultTypeRuleSourceInterface;
use Hryvinskyi\RichSnippets\Model\Config\ModuleJsonFile;

/**
 * A rule definition shipped as a JSON file in a module: `etc/rich_snippets/result_types/<file>` of that module
 *
 * Configured per rule in di.xml with the module's name and the file's name, so a module adds a rule without code.
 */
class ModuleRuleFile implements ResultTypeRuleSourceInterface
{
    public const DIRECTORY = 'rich_snippets/result_types';

    /**
     * @param ModuleJsonFile $files
     * @param string $moduleName the module that ships the file (`Vendor_Module`)
     * @param string $fileName the file's name in the module's rule directory (`merchant_listing.json`)
     */
    public function __construct(
        private readonly ModuleJsonFile $files,
        private readonly string $moduleName,
        private readonly string $fileName
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(): array
    {
        try {
            return $this->files->read($this->moduleName, self::DIRECTORY, $this->fileName);
        } catch (\UnexpectedValueException $e) {
            throw new MalformedRuleException($e->getMessage(), 0, $e);
        }
    }
}
