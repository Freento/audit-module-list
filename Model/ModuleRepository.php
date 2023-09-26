<?php

declare(strict_types=1);

namespace Freento\AuditModuleList\Model;

use Exception;
use Freento\AuditModuleList\Api\Data\ModuleInterface;
use Freento\AuditModuleList\Api\ModuleRepositoryInterface;
use Magento\Framework\Module\FullModuleList;

class ModuleRepository implements ModuleRepositoryInterface
{
    /**
     * For creating an instance of class Module
     *
     * @var ModuleFactory
     */
    private ModuleFactory $moduleFactory;

    /**
     * @param ModuleFactory $moduleFactory
     */
    public function __construct(
        ModuleFactory $moduleFactory
    ) {
        $this->moduleFactory = $moduleFactory;
    }

    /**
     * Returns a module info: name, version, the latest version, url on zip archive of module
     *
     * @param string $moduleName
     * @return ModuleInterface
     * @throws Exception
     */
    public function getModuleByName(string $moduleName): ModuleInterface
    {
        $module = $this->moduleFactory->create();
        $module->prepareData($moduleName);
        return $module;
    }

    /**
     * Returns list of modules
     *
     * @return ModuleInterface[]
     * @throws Exception
     */
    public function getList(): array
    {
        $allModules = $this->moduleFactory->create()->getFullModuleList();
        $resultArray = [];

        foreach ($allModules as $module) {
            $moduleObject = $this->moduleFactory->create();
            $moduleObject->prepareData($module);
            $resultArray[] = $moduleObject;
        }

        return $resultArray;
    }
}
