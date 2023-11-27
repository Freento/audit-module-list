<?php

declare(strict_types=1);

namespace Freento\AuditModuleList\Block\Adminhtml;

use Composer\Package\Version\VersionParser;
use Freento\AuditModuleList\Api\Data\ModuleInterface;
use Freento\AuditModuleList\Model\Module;
use Freento\AuditModuleList\Model\ModuleRepository;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Report extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Freento_AuditModuleList::report.phtml';

    /**
     * @var ModuleRepository
     */
    private ModuleRepository $moduleRepo;

    /**
     * @param Context $context
     * @param ModuleRepository $moduleRepo
     */
    public function __construct(Context $context, ModuleRepository $moduleRepo)
    {
        parent::__construct($context);
        $this->moduleRepo = $moduleRepo;
    }

    /**
     * Returns module list
     *
     * @return ModuleInterface[]
     * @throws \Exception
     */
    public function getModuleList(): array
    {
        return $this->moduleRepo->getList();
    }

    /**
     * Returns CSS class for module status
     *
     * @param int $status
     * @return string
     */
    public function getStatusCssClass(int $status): string
    {
        $class = '';
        switch ($status) {
            case Module::STATUS_DISABLED:
                $class = 'module-disabled';
                break;
            case Module::STATUS_UNKNOWN:
                $class = 'module-unknown';
                break;
        }

        return $class;
    }

    /**
     * Returns text representation of status
     *
     * @param int $status
     * @return string
     */
    public function getStatusCaption(int $status): string
    {
        $caption = '';
        switch ($status) {
            case Module::STATUS_ENABLED:
                $caption = 'Enabled';
                break;
            case Module::STATUS_DISABLED:
                $caption = 'Disabled';
                break;
            case Module::STATUS_UNKNOWN:
                $caption = 'Unknown';
                break;
        }

        return $caption;
    }

    /**
     * Checks if update for given module is available
     *
     * @param ModuleInterface $module
     * @return bool
     */
    public function isUpdateAvailable(ModuleInterface $module): bool
    {
        $current = $module->getVersion();
        $latest = $module->padLatestVersion();
        if ($current === ModuleInterface::VERSION_NOT_FOUND || $latest === ModuleInterface::PARAMETER_N_A) {
            return false;
        }

        return version_compare($current, $latest, '<');
    }
}
