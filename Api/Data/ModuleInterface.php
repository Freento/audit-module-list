<?php

declare(strict_types=1);

namespace Freento\AuditModuleList\Api\Data;

interface ModuleInterface
{
    public const NAME = 'module_name';
    public const VERSION = 'module_version';
    public const LATEST_VERSION = 'latest_version';
    public const INSTALLATION_TYPE = 'installation_type';
    public const PARAMETER_N_A = 'N/A';
    public const APP_CODE_INSTALLATION = 'app/code';
    public const VENDOR = 'Composer';
    public const VERSION_NOT_FOUND = 'Version not found';
    public const COMPOSER_PATH = DIRECTORY_SEPARATOR . 'composer.json';
    public const COMPOSER_LOCK_PATH = DIRECTORY_SEPARATOR . 'composer.lock';
    public const MAX_VERSION = 0;

    /**
     * Puts all necessary data in the Module model: Module name, version, latest version
     *
     * @param string $moduleName
     */
    public function prepareData(string $moduleName);

    /**
     * Returns custom module list
     *
     * @return array
     */
    public function getFullModuleList(): array;

    /**
     * Returns module name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns module version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Returns module latest version on magento repository
     *
     * @return string
     */
    public function getLatestVersion(): string;

    /**
     * Returns installation type of module. It can be: app/code, composer, N/A
     *
     * @return string
     */
    public function getInstallationType(): string;

    /**
     * Returns module status
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * Set module name
     *
     * @param string $moduleName
     * @return void
     */
    public function setName(string $moduleName): void;

    /**
     * Set module version
     *
     * @param string $moduleVersion
     * @return void
     */
    public function setVersion(string $moduleVersion): void;

    /**
     * Set latest module version
     *
     * @param string $latestVersion
     * @return void
     */
    public function setLatestVersion(string $latestVersion): void;

    /**
     * Set installation type of module
     *
     * @param string $installationType
     * @return void
     */
    public function setInstallationType(string $installationType): void;

    /**
     * Sets module status
     *
     * @param int $enabled
     * @return void
     */
    public function setStatus(int $enabled): void;
}
