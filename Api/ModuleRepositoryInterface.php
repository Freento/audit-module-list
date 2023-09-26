<?php

declare(strict_types=1);

namespace Freento\AuditModuleList\Api;

use Freento\AuditModuleList\Api\Data\ModuleInterface;

interface ModuleRepositoryInterface
{
    /**
     * Returns a module info: name, version, latest version, url on zip archive of module
     *
     * @param string $moduleName
     * @return ModuleInterface
     */
    public function getModuleByName(string $moduleName): ModuleInterface;

    /**
     * Returns list of modules
     *
     * @return ModuleInterface[]
     */
    public function getList(): array;
}
