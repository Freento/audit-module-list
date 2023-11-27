<?php

declare(strict_types=1);

namespace Freento\AuditModuleList\Model;

use Composer\Downloader\TransportException;
use Composer\IO\NullIO;
use Composer\Package\BasePackage;
use Composer\Util\Platform;
use Composer\Json\JsonValidationException;
use Exception;
use Freento\AuditModuleList\Api\Data\ModuleInterface;
use Freento\AuditModuleList\Exception\ModulesConfigNotFoundException;
use Magento\Framework\App\DeploymentConfig\Reader;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\FullModuleList;
use Freento\AuditModuleList\Exception\SetupException;
use Freento\AuditModuleList\Exception\NoRepositoriesException;
use Freento\AuditModuleList\Exception\NoPropertyException;
use Freento\AuditModuleList\Exception\NoDocumentRootException;
use Freento\AuditModuleList\Exception\CannotReadXmlException;
use Freento\AuditModuleList\Exception\CantCreateComposerInstanceException;
use Freento\AuditModuleList\Exception\ModuleNotRegisteredException;
use Freento\AuditModuleList\Exception\WrongRepoLinkException;
use Magento\Composer\MagentoComposerApplicationFactory as Factory;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;

class Module extends DataObject implements ModuleInterface
{
    private const JSON_ERROR_MESSAGE = 'Wrong composer.json file for module %1';
    private const COMPOSER_LOCK_ERROR_MESSAGE = 'Wrong composer.lock file';
    private const NO_REPOSITORIES_ERROR_MESSAGE = 'No repositories found';
    private const PROPERTY_NOT_FOUND_MESSAGE = 'Property %1 not found in %2';
    private const DOCUMENT_ROOT_NOT_FOUND_MESSAGE = 'Document root not found';
    private const CAN_NOT_READ_XML_MESSAGE = 'Can not read xml file at %1';
    private const COMPOSER_FILE_NOT_EXIST_MESSAGE = 'Can not create composer in %1 : file not exist or corrupted';
    private const MODULE_NOT_REGISTERED_ERROR_MESSAGE = 'Module with name %1 not found or not registered';
    private const WRONG_REPO_LINK_ERROR_MESSAGE = 'Wrong repository link: %1';
    private const STATUS = 'status';

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;
    public const STATUS_UNKNOWN = 2;

    /**
     * @var array
     */
    private static array $modulesStatus;

    /**
     * For getting full list of installed modules
     *
     * \Freento\AuditModuleList\Model\ModuleFactory
     * @var FullModuleList
     */
    private FullModuleList $fullModuleList;

    /**
     * For getting the dir of module
     *
     * @var Dir
     */
    private Dir $dir;

    /**
     * @var File
     */
    private File $driver;

    /**
     * @var Factory
     */
    private Factory $composerFactory;

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * @var Reader
     */
    private Reader $reader;

    /**
     * @param FullModuleList $fullModuleList
     * @param Dir $dir
     * @param File $driver
     * @param Factory $composerFactory
     * @param DirectoryList $directoryList
     * @param Reader $reader
     * @param array $data
     * @throws Exception
     */
    public function __construct(
        FullModuleList $fullModuleList,
        Dir $dir,
        File $driver,
        Factory $composerFactory,
        DirectoryList $directoryList,
        Reader $reader,
        array $data = []
    ) {
        $this->fullModuleList = $fullModuleList;
        $this->dir = $dir;
        $this->driver = $driver;
        $this->composerFactory = $composerFactory;
        $this->directoryList = $directoryList;
        $this->reader = $reader;
        parent::__construct($data);
    }

    /**
     * Loads module status from config.php
     *
     * @param string $moduleName
     * @return int
     * @throws FileSystemException
     * @throws ModulesConfigNotFoundException
     * @throws RuntimeException
     */
    private function getModuleStatus(string $moduleName): int
    {
        if (!empty(self::$modulesStatus)) {
            return self::$modulesStatus[$moduleName];
        }

        $currentConfig = $this->reader->load(ConfigFilePool::APP_CONFIG);
        if (!array_key_exists(ConfigOptionsListConstants::KEY_MODULES, $currentConfig)) {
            throw new ModulesConfigNotFoundException();
        }

        self::$modulesStatus = $currentConfig[ConfigOptionsListConstants::KEY_MODULES];
        self::$modulesStatus = array_filter(self::$modulesStatus, [$this, 'nonMagentoModule'], ARRAY_FILTER_USE_KEY);
        self::$modulesStatus = array_filter(self::$modulesStatus, [$this, 'nonFreentoAudit'], ARRAY_FILTER_USE_KEY);

        return self::$modulesStatus[$moduleName];
    }

    /**
     * Get module name
     *
     * @param string $module
     * @return string
     * @throws FileSystemException
     * @throws NoPropertyException
     * @throws CannotReadXmlException
     * @throws ModuleNotRegisteredException
     */
    private function getModuleName(string $module): string
    {
        // Constants are not allowed in translate function
        $composerPath = self::COMPOSER_PATH;
        $moduleNotRegistered = self::MODULE_NOT_REGISTERED_ERROR_MESSAGE;
        try {
            $path = $this->dir->getDir($module);
        } catch (\InvalidArgumentException $e) {
            throw new ModuleNotRegisteredException(__($moduleNotRegistered, $module));
        }

        $propertyNotFoundMessage = self::PROPERTY_NOT_FOUND_MESSAGE;
        if ($this->driver->isExists($path . $composerPath)) {
            $content = $this->driver->fileGetContents($path . $composerPath);
            $jsonContent = json_decode($content);
            if (isset($jsonContent->name)) {
                $name = json_decode($content)->name;
            } else {
                throw new NoPropertyException(
                    __($propertyNotFoundMessage, 'name', $composerPath)
                );
            }
        } else {
            $path = $path . $this->fileBuildPath('etc', 'module.xml');
            $xmlExceptionMessage = self::CAN_NOT_READ_XML_MESSAGE;
            if ($this->driver->isExists($path)) {
                try {
                    $xmlContent = simplexml_load_string($this->driver->fileGetContents($path));
                } catch (FileSystemException $e) {
                    throw new CannotReadXmlException(__($xmlExceptionMessage, $path));
                }
                if (isset($xmlContent->module['name'])) {
                    $name = (string)$xmlContent->module['name'];
                } else {
                    throw new NoPropertyException(__($propertyNotFoundMessage, 'name', $path));
                }
            } else {
                $name = self::PARAMETER_N_A;
            }
        }

        return $name;
    }

    /**
     * An expression that checks the stability of a module callback to filter_array
     *
     * @param BasePackage $module
     * @return bool
     */
    private function isStable(BasePackage $module): bool
    {
        return (string)$module->getStability() === 'stable';
    }

    /**
     * Getting the output of composer and returning latest stable version
     *
     * @param string $composerModuleName
     * @return string
     * @throws JsonValidationException
     * @throws NoRepositoriesException
     * @throws SetupException
     * @throws LocalizedException
     */
    public function getLatestModuleVersion(string $composerModuleName): string
    {
        $documentRoot = $this->directoryList->getRoot();

        $noDocumentRootException = self::DOCUMENT_ROOT_NOT_FOUND_MESSAGE;
        if (!isset($documentRoot)) {
            // Constants are not allowed in translate function
            throw new NoDocumentRootException(__($noDocumentRootException));
        }

        try {
            $composerInstance = $this->composerFactory->create([
                'pathToComposerHome' => $documentRoot . $this->fileBuildPath('var', 'composer_home'),
                'pathToComposerJson' => $documentRoot . self::COMPOSER_PATH
            ])->createComposer();
            $repositoryManager = $composerInstance->getRepositoryManager();
            $repositories = $repositoryManager->getRepositories();
        } catch (JsonValidationException $e) {
            $composerExceptionMessage = self::COMPOSER_FILE_NOT_EXIST_MESSAGE;
            throw new CantCreateComposerInstanceException(
                __($composerExceptionMessage, $documentRoot
                    . self::COMPOSER_PATH)
            );
        }

        $noRepositoriesMessage = self::NO_REPOSITORIES_ERROR_MESSAGE;
        if (!isset($repositories)) {
            throw new NoRepositoriesException(__($noRepositoriesMessage));
        }

        $composerModulesList = [];
        foreach ($repositories as $repo) {
            if (isset($repo)) {
                try {
                    if (isset($repo->getRepoConfig()['type']) && $repo->getRepoConfig()['type'] === 'artifact') {
                        // phpcs:disable Magento2.Functions.DiscouragedFunction
                        $cwd = getcwd();
                        chdir($documentRoot);
                        $composerModulesList = $repo->findPackages($composerModuleName);
                        chdir($cwd);
                        // phpcs:enable Magento2.Functions.DiscouragedFunction
                    } else {
                        $composerModulesList = $repo->findPackages($composerModuleName);
                    }
                } catch (TransportException $e) {
                    $wrongRepoMessage = self::WRONG_REPO_LINK_ERROR_MESSAGE;
                    throw new WrongRepoLinkException(__($wrongRepoMessage, $repo->getRepoName()));
                }

                if (!empty($composerModulesList)) {
                    break;
                }
            }
        }

        $composerModulesList = array_filter($composerModulesList, [$this, 'isStable']); // get only stable versions

        $maxVersion = self::PARAMETER_N_A;
        if (!empty($composerModulesList)) {
            $maxVersion = self::MAX_VERSION;
            foreach ($composerModulesList as $composerModule) {
                if (isset($composerModule)) {
                    if (version_compare($composerModule->getPrettyVersion(), $maxVersion, '>')) {
                        $maxVersion = $composerModule->getPrettyVersion();
                    }
                }
            }
        }

        return $maxVersion;
    }

    /**
     * Checks the existence of substring "Magento_" in $moduleName. Callback for function array_filter
     *
     * @param string $moduleName
     * @return bool
     */
    private function nonMagentoModule(string $moduleName): bool
    {
        return strpos($moduleName, 'Magento_') === false;
    }

    /**
     * Checks the existence of substring "Freento_Audit" in $moduleName.
     * Needed for excluding Modules of Freento vendor
     * Callback for function array_filter
     *
     * @param string $moduleName
     * @return bool
     */
    private function nonFreentoAudit(string $moduleName): bool
    {
        return strpos($moduleName, 'Freento_Audit') === false;
    }

    /**
     * Filtering module list (exclude Magento and Freento_Audit modules)
     *
     * @param array $moduleList
     * @return array
     */
    private function prepareModuleList(array $moduleList): array
    {
        $allModules = array_filter($moduleList, [$this, 'nonMagentoModule']);
        $allModules = array_filter($allModules, [$this, 'nonFreentoAudit']);
        $allModules = array_values($allModules);

        return $allModules;
    }

    /**
     * Puts all necessary data in the Module model: Module name, version, the latest version
     *
     * @param string $moduleName
     * @throws CannotReadXmlException
     * @throws FileSystemException
     * @throws JsonValidationException
     * @throws LocalizedException
     * @throws ModuleNotRegisteredException
     * @throws NoPropertyException
     * @throws NoRepositoriesException
     * @throws SetupException
     */
    public function prepareData(string $moduleName)
    {
        $defaultVersion = self::PARAMETER_N_A;
        $version = $defaultVersionTranslated = __($defaultVersion);

        $composerModuleName = $this->getModuleName($moduleName);
        $latestVersion = $this->getLatestModuleVersion($composerModuleName);

        $path = $this->dir->getDir($moduleName);
        $installationType = stristr($path, self::APP_CODE_INSTALLATION)
            ? self::APP_CODE_INSTALLATION : self::VENDOR;
        // if composer.json exist we use composer.json as source for our modules data
        if ($this->driver->isExists($path . self::COMPOSER_PATH)) {
            $content = $this->driver->fileGetContents($path . self::COMPOSER_PATH);

            if (empty($content)) {
                $jsonErrorMessage = self::JSON_ERROR_MESSAGE;
                throw new SetupException(__($jsonErrorMessage, $composerModuleName));
            }

            $jsonData = json_decode($content);
            $version = $jsonData->version ?? $defaultVersionTranslated;
        }
        // if composer.json does not exist or does not contain version we retrieve version from global composer.lock
        if (
            $version === $defaultVersionTranslated
            && $installationType === self::VENDOR
            && $this->driver->isExists($this->directoryList->getRoot() . self::COMPOSER_LOCK_PATH)
        ) {
            $content = $this->driver->fileGetContents($this->directoryList->getRoot() . self::COMPOSER_LOCK_PATH);

            if (empty($content)) {
                $composerLockErrorMessage = self::COMPOSER_LOCK_ERROR_MESSAGE;
                throw new SetupException(__($composerLockErrorMessage));
            }

            $jsonData = json_decode($content, true);
            foreach ($jsonData['packages'] as $package) {
                if ($package['name'] === $composerModuleName) {
                    $version = $package['version'] ?? $defaultVersionTranslated;
                    break;
                }
            }
        }
        // if the version wasn't retrieved from Composer we use module.xml as source for our modules data
        if ($version === $defaultVersionTranslated) {
            $path .= $this->fileBuildPath('etc', 'module.xml');

            if ($this->driver->isExists($path)) {
                $xmlContent = simplexml_load_string($this->driver->fileGetContents($path));
                $version = isset($xmlContent->module['setup_version'])
                    ? $xmlContent->module['setup_version'] . __(' (setup_version)')
                    : self::VERSION_NOT_FOUND;
            }
        }

        $this->setName($moduleName);
        $this->setVersion((string)$version);
        $this->setLatestVersion($latestVersion);
        $this->setInstallationType($installationType);
        $this->setStatus($this->getModuleStatus($moduleName) ?? self::STATUS_UNKNOWN);
    }

    /**
     * Returns full module list
     *
     * @return array
     */
    public function getFullModuleList(): array
    {
        $modulesList = $this->fullModuleList->getNames();
        $modulesList = $this->prepareModuleList($modulesList);
        return $modulesList;
    }

    /**
     * Returns module name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->getData(self::NAME);
    }

    /**
     * Returns module version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->getData(self::VERSION);
    }

    /**
     * Returns latest module version on magento repository
     *
     * @return string
     */
    public function getLatestVersion(): string
    {
        return $this->getData(self::LATEST_VERSION);
    }

    /**
     * Return module installation type. It can be: app/code, composer, N/A
     *
     * @return string
     */
    public function getInstallationType(): string
    {
        return $this->getData(self::INSTALLATION_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): int
    {
        return (int)$this->getData(self::STATUS);
    }

    /**
     * Set module name
     *
     * @param string $moduleName
     * @return void
     */
    public function setName(string $moduleName): void
    {
        $this->setData(self::NAME, $moduleName);
    }

    /**
     * Set module Version
     *
     * @param string $moduleVersion
     * @return void
     */
    public function setVersion(string $moduleVersion): void
    {
        $this->setData(self::VERSION, $moduleVersion);
    }

    /**
     * Set latest module version
     *
     * @param string $latestVersion
     * @return void
     */
    public function setLatestVersion(string $latestVersion): void
    {
        $this->setData(self::LATEST_VERSION, $latestVersion);
    }

    /**
     * Set module installation type
     *
     * @param string $installationType
     * @return void
     */
    public function setInstallationType(string $installationType): void
    {
        $this->setData(self::INSTALLATION_TYPE, $installationType);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(int $enabled): void
    {
        $this->setData(self::STATUS, $enabled);
    }

    /**
     * Assembles file path with separator
     *
     * @param string[] $segments
     * @return string
     */
    private function fileBuildPath(string ...$segments): string
    {
        return DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Pads latest version number to the length of current version number without losing important digits
     *
     * @return string
     */
    public function padLatestVersion(): string
    {
        $latest = $this->getLatestVersion();
        if ($latest === ModuleInterface::PARAMETER_N_A) {
            return $latest;
        }

        $current = preg_replace(['/ (setup_version)/', '/^v/'], '', $this->getVersion());
        $pattern = '/(\d+(\.\d+)+)([-+\w].*)?/';
        if (!preg_match($pattern, $current, $currentMatches) || !preg_match($pattern, $latest, $latestMatches)) {
            return $latest;
        }

        if (!isset($currentMatches[1]) || !isset($latestMatches[1])) {
            return $latest;
        }

        $currentParts = explode('.', $currentMatches[1]);
        $latestParts = explode('.', $latestMatches[1]);

        $digitsInCurrent = count($currentParts);
        $digitsInLatest = count($latestParts);
        if ($digitsInCurrent < $digitsInLatest) {
            for ($i = array_key_last($latestParts); $i > array_key_last($currentParts); $i--) {
                if ($latestParts[$i] === '0') {
                    unset($latestParts[$i]);
                } else {
                    break;
                }
            }
        } elseif ($digitsInCurrent > $digitsInLatest) {
            $latestParts = array_pad($latestParts, $digitsInCurrent, '0');
        }
        $latestMatches[1] = implode('.', $latestParts);

        return $latestMatches[1] . ($latestMatches[3] ?? '');
    }
}
