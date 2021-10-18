<?php
/**
 * Copyright (C) 2018-2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2018-2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

use CoreUpdater\Api\ThirtybeesApiException;
use CoreUpdater\DatabaseSchemaComparator;
use CoreUpdater\Factory;
use CoreUpdater\InformationSchemaBuilder;
use CoreUpdater\ObjectModelSchemaBuilder;
use CoreUpdater\Process\ProcessingState;
use CoreUpdater\Process\Processor;
use CoreUpdater\SchemaDifference;
use CoreUpdater\Settings;

require_once __DIR__ . '/../../classes/Factory.php';

/**
 * Class AdminCoreUpdaterController.
 */
class AdminCoreUpdaterController extends ModuleAdminController
{
    const PARAM_TAB = 'tab';
    const TAB_UPDATE = 'update';
    const TAB_SETTINGS = 'settings';
    const TAB_DB = 'database';

    /**
     * Where manually modified files get backed up before they get overwritten
     * by the new version. A directory path, which gets appended by a date of
     * the format BACKUP_DATE_SUFFIX (should give a unique suffix).
     */
    const BACKUP_PATH = _PS_ADMIN_DIR_.'/CoreUpdaterBackup';
    const BACKUP_DATE_SUFFIX = '-Y-m-d--H-i-s';

    const ACTION_GET_VERSIONS = 'GET_VERSIONS';
    const ACTION_SAVE_SETTINGS = 'SAVE_SETTINGS';
    const ACTION_CLEAR_CACHE = 'CLEAR_CACHE';
    const ACTION_COMPARE_PROCESS = "COMPARE";
    const ACTION_INIT_UPDATE = "INIT_UPDATE";
    const ACTION_UPDATE_PROCESS = "UPDATE";
    const ACTION_GET_DATABASE_DIFFERENCES = "GET_DATABASE_DIFFERENCES";
    const ACTION_APPLY_DATABASE_FIX = 'APPLY_DATABASE_FIX';

    /**
     * @var Factory
     */
    private $factory;

    /**
     * AdminCoreUpdaterController constructor.
     *
     * @version 1.0.0 Initial version.
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $link = Context::getContext()->link;
        $baseLink = $link->getBaseLink();
        $this->factory = new Factory(
            Settings::getApiServer(),
            $baseLink,
            _PS_ROOT_DIR_,
            _PS_ADMIN_DIR_,
            _PS_TOOL_DIR_ . '/cacert.pem'
        );
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    private function initUpdateTab()
    {
        $version = $this->findVersionToUpdate();
        $comparator = $this->factory->getComparator();

        if ($version['stable']) {
            $versionName = $version['version'];
            $versionType = $this->l('stable');
        } else {
            $versionName = $version['revision'];
            $versionType = $this->l('bleeding edge');
        }

        $processId = $comparator->startProcess([
            'ignoreTheme' => !Settings::syncThemes(),
            'targetRevision' => $version['revision'],
            'targetVersion' => $version['version'],
            'versionName' => $versionName,
            'versionType' => $versionType,
        ]);


        $this->content .= $this->render('error');
        $this->content .= $this->render('tab_update', [
            'updateMode' => Settings::getUpdateMode(),
            'targetVersion' => [
                'version' => $versionName,
                'type' => $versionType,
            ],
            'process' => [
                'id' => $processId,
                'status' => ProcessingState::IN_PROGRESS,
                'progress' => 0.0,
                'step' => $comparator->describeCurrentStep($processId),
            ]
        ]);
    }

    /**
     * @return array
     * @throws PrestaShopException
     * @throws ThirtybeesApiException
     */
    private function findVersionToUpdate()
    {
        $api = $this->factory->getApi();
        $versions = $api->getVersions();
        $stable = Settings::getUpdateMode() === Settings::UPDATE_MODE_STABLE;
        $logger = $this->factory->getLogger();
        $logger->log("Resolving latest version for " . Settings::getUpdateMode());
        foreach ($versions as $version) {
            if ($version['stable'] === $stable) {
                $logger->log("Latest version = " . json_encode($version, JSON_PRETTY_PRINT));
                return $version;
            }
        }
        $logger->error("Failed to resolve latest version");
        throw new PrestaShopException("No version has been found");
    }

    /**
     *  Method to set up page for Settings  tab
     */
    private function initSettingsTab()
    {
        $this->fields_options = [
            'distributionChannel' => [
                'title'       => $this->l('Distribution channel'),
                'icon'        => 'icon-cogs',
                'description' => (
                    '<p>'
                    .$this->l('Here you can choose thirty bees distribution channel')
                    .'</p>'
                    .'<ul>'
                    .'<li><b>'.$this->l('Stable releases').'</b>&nbsp;&mdash;&nbsp;'
                    .$this->l("Your store will be updated to stable official releases only. This is recommended settings for production stores")
                    .'</li>'
                    .'<li><b>'.$this->l('Bleeding edge').'</b>&nbsp;&mdash;&nbsp;'
                    .$this->l("Your store will be updated to latest build. This will allow you to test new features early. This is recommended settings for testing sites.")
                    .'</li>'
                    .'</ul>'
                ),
                'submit'      => [
                    'title'     => $this->l('Save'),
                    'imgclass'  => 'save',
                    'name'      => static::ACTION_SAVE_SETTINGS,
                ],
                'fields' => [
                    Settings::SETTINGS_UPDATE_MODE => [
                        'type'        => 'select',
                        'title'       => $this->l('Distribution channel'),
                        'identifier'  => 'mode',
                        'list'        => [
                            [
                                'mode' => Settings::UPDATE_MODE_STABLE,
                                'name' => $this->l('Stable releases')
                            ],
                            [
                                'mode' => Settings::UPDATE_MODE_BLEEDING_EDGE,
                                'name' => $this->l('Bleeding edge')
                            ],
                        ],
                        'no_multishop_checkbox' => true,
                    ],
                ],
            ],
            'settings' => [
                'title'       => $this->l('Update settings'),
                'icon'        => 'icon-cogs',
                'submit'      => [
                    'title'     => $this->l('Save'),
                    'imgclass'  => 'save',
                    'name'      => static::ACTION_SAVE_SETTINGS,
                ],
                'fields' => [
                    Settings::SETTINGS_SYNC_THEMES => [
                        'type'       => 'bool',
                        'title'      => $this->l('Update community themes'),
                        'desc'       => $this->l('When enabled, community themes will be updated together with core code. Enable this option only if you didn\'t modify community theme'),
                        'no_multishop_checkbox' => true,
                    ],
                    Settings::SETTINGS_SERVER_PERFORMANCE => [
                        'type' => 'select',
                        'title' => $this->l('Server performance'),
                        'desc' => $this->l('This settings option allows you to fine tune amount of work that will be performed during single update step. If you experience any timeout issue, please lower this settings'),
                        'identifier'  => 'key',
                        'no_multishop_checkbox' => true,
                        'list' => [
                            [
                                'key' => Settings::PERFORMANCE_LOW,
                                'name' => $this->l('Low - shared hosting with limited resources')
                            ],
                            [
                                'key' => Settings::PERFORMANCE_NORMAL,
                                'name' => $this->l('Normal - generic hosting')
                            ],
                            [
                                'key' => Settings::PERFORMANCE_HIGH,
                                'name' => $this->l('High - dedicated server')
                            ],
                        ]
                    ],
                ],
            ],
            'cache' => [
                'title'       => $this->l('Cache'),
                'icon'        => 'icon-refresh',
                'description' => (
                    sprintf($this->l("Clear cached information retrieved from api server '%s'"), Settings::getApiServer())
                ),
                'submit'      => [
                    'title'     => $this->l('Clear cache'),
                    'imgclass'  => 'refresh',
                    'name'      => static::ACTION_CLEAR_CACHE,
                ],
                'fields' => [
                ],
            ],
            'advanced' => [
                'title'       => $this->l('Advanced settings'),
                'icon'        => 'icon-cogs',
                'submit'      => [
                    'title'     => $this->l('Save'),
                    'imgclass'  => 'save',
                    'name'      => static::ACTION_SAVE_SETTINGS,
                ],
                'fields' => [
                    Settings::SETTINGS_API_TOKEN => [
                        'type'       => 'text',
                        'title'      => $this->l('Token'),
                        'desc'       => $this->l('Secret token for communication with thirtybees api. Optional'),
                        'no_multishop_checkbox' => true,
                    ],
                ]
            ],
        ];
    }

    /**
     *  Method to set up page for Database Differences tab
     *
     * @throws SmartyException
     */
    private function initDatabaseTab()
    {
        if (class_exists('CoreModels')) {
            $description = $this->l('This tool helps you discover and fix problems with database schema');
            $info = $this->render('schema-differences');
            $this->fields_options = [
                'database' => [
                    'title' => $this->l('Database schema'),
                    'description' => $description,
                    'icon' => 'icon-beaker',
                    'info' => $info,
                    'submit' => [
                        'id' => 'refresh-btn',
                        'title'     => $this->l('Refresh'),
                        'imgclass'  => 'refresh',
                        'name'      => 'refresh',
                    ]
                ]
            ];
        } else {
            $info = (
                '<div class=\'alert alert-warning\'>' .
                $this->l('This version of thirty bees does not support database schema comparison and migration') .
                '</div>'
            );
            $this->fields_options = [
                'database_incompatible' => [
                    'title' => $this->l('Database schema'),
                    'icon' => 'icon-beaker',
                    'info' => $info
                ]
            ];
        }
        $this->content .= $this->render("error");
    }

    /**
     * Get back office page HTML.
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function initContent()
    {
        $this->addCSS(_PS_MODULE_DIR_.'coreupdater/views/css/coreupdater.css');
        Shop::setContext(Shop::CONTEXT_ALL);
        $this->page_header_toolbar_title = $this->l('Core Updater');
        $this->factory->getErrorHandler()->handleErrors([$this, 'performInitContent']);
        parent::initContent();
    }

    /**
     * @throws SmartyException
     */
    public function performInitContent()
    {
        try {
            if ($this->checkModuleVersion()) {
                $currentVersion = $this->module->version;
                $latestVersion = Settings::getLatestModuleVersion();
                if (version_compare($currentVersion, $latestVersion, '<')) {
                    $this->content .= $this->render('new-version', [
                        'currentVersion' => $currentVersion,
                        'latestVersion' => $latestVersion,
                    ]);
                }
                switch ($this->getActiveTab()) {
                    case static::TAB_UPDATE:;
                        $this->initUpdateTab();
                        break;
                    case static::TAB_SETTINGS:
                        $this->initSettingsTab();
                        break;
                    case static::TAB_DB:
                        $this->initDatabaseTab();
                        break;
                }
            }
        } catch (Exception $e) {
            $this->content .= $this->render('error', [
                'errorMessage' => $e->getMessage(),
                'errorDetails' => $e->__toString()
            ]);
        }
    }

    /**
     * @throws SmartyException
     */
    public function display()
    {
        $this->context->smarty->assign('help_link', null);
        parent::display();
    }

    /**
     * @throws PrestaShopException
     */
    public function initToolbar()
    {
        switch ($this->getActiveTab()) {
            case static::TAB_UPDATE:
                $this->addDatabaseButton();
                $this->addSettingsButton();
                break;
            case static::TAB_DB:
                $this->addUpdateButton();
                $this->addSettingsButton();
                break;
            case static::TAB_SETTINGS:
                $this->addDatabaseButton();
                $this->addUpdateButton();
                break;
        }
        parent::initToolbar();
    }

    /**
     * @throws PrestaShopException
     */
    private function addDatabaseButton()
    {
        $this->page_header_toolbar_btn['db'] = [
            'icon' => 'process-icon-database',
            'href' => static::tabLink(static::TAB_DB),
            'desc' => $this->l('Database'),
        ];
    }

    /**
     * @throws PrestaShopException
     */
    private function addSettingsButton()
    {
        $this->page_header_toolbar_btn['settings'] = [
            'icon' => 'process-icon-cogs',
            'href' => static::tabLink(static::TAB_SETTINGS),
            'desc' => $this->l('Settings'),
        ];
    }

    /**
     * @throws PrestaShopException
     */
    private function addUpdateButton()
    {
        $this->page_header_toolbar_btn['update'] = [
            'icon' => 'process-icon-download',
            'href' => static::tabLink(static::TAB_UPDATE),
            'desc' => $this->l('Check updates'),
        ];
    }

    /**
     * Set media.
     *
     * @version 1.0.0 Initial version.
     * @throws PrestaShopException
     */
    public function setMedia()
    {
        parent::setMedia();

        $this->addJquery();
        $this->addJS(_PS_MODULE_DIR_.'coreupdater/views/js/controller.js');
    }

    /**
     * Post processing. All custom code, no default processing used.
     *
     * @version 1.0.0 Initial version.
     */
    public function postProcess()
    {
        $this->factory->getErrorHandler()->handleErrors([$this, 'performPostProcess']);
        // Intentionally not calling parent, there's nothing to do.
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function performPostProcess()
    {

        if (Tools::getValue('ajax') && Tools::getValue('action')) {
            // process ajax action
            $this->ajaxProcess(Tools::getValue('action'));
        }

        if (Tools::isSubmit(static::ACTION_SAVE_SETTINGS)) {
            Settings::setUpdateMode(Tools::getValue(Settings::SETTINGS_UPDATE_MODE));
            Settings::setSyncThemes(!!Tools::getValue(Settings::SETTINGS_SYNC_THEMES));
            Settings::setServerPerformance(Tools::getValue(Settings::SETTINGS_SERVER_PERFORMANCE));
            Settings::setApiToken(Tools::getValue(Settings::SETTINGS_API_TOKEN));
            $this->context->controller->confirmations[] = $this->l('Settings saved');
            $this->setRedirectAfter(static::tabLink(static::TAB_SETTINGS));
            $this->redirect();
        }

        if (Tools::isSubmit(static::ACTION_CLEAR_CACHE)) {
            $this->factory->getStorageFactory()->flush();
            $this->context->controller->confirmations[] = $this->l('Cache cleared');
            $this->setRedirectAfter(static::tabLink(static::TAB_SETTINGS));
            $this->redirect();
        }
    }

    /**
     * @param string $action
     */
    protected function ajaxProcess($action)
    {
        $logger = $this->factory->getLogger();
        $logger->log('Action ' . $action);
        try {
            die(json_encode([
                'success' => true,
                'data' => $this->processAction($action)
            ]));
        } catch (Exception $e) {
            $logger->error('Failed to process action ' . $action . ': ' . $e->getMessage() . ': ' . $e->getTraceAsString());
            die(json_encode([
                'success' => false,
                'error' => [
                    'message' => $e->getMessage(),
                    'details' => $e->__toString()
                ]
            ]));
        }
    }

    /**
     * @param string $action action to process
     * @return mixed
     * @throws Exception
     */
    protected function processAction($action)
    {
        switch ($action) {
            case static::ACTION_GET_VERSIONS:
                return $this->getVersions();
            case static::ACTION_COMPARE_PROCESS:
                return $this->compareProcess(Tools::getValue('processId'));
            case static::ACTION_INIT_UPDATE:
                return $this->initUpdateProcess();
            case static::ACTION_UPDATE_PROCESS:
                return $this->updateProcess(Tools::getValue('processId'));
            case static::ACTION_GET_DATABASE_DIFFERENCES:
                return $this->getDatabaseDifferences();
            case static::ACTION_APPLY_DATABASE_FIX:
                return $this->applyDatabaseFix(Tools::getValue('ids'));
            default:
                throw new Exception("Invalid action: $action");
        }
    }

    /**
     * @return array
     * @throws ThirtybeesApiException
     */
    protected function getVersions()
    {
        return $this->factory->getApi()->getVersions();
    }

    /**
     * @param string $processId
     * @return array
     * @throws Exception
     */
    protected function updateProcess($processId)
    {
        return $this->runProcess(
            $this->factory->getUpdater(),
            $processId,
            function($result) {
                return [
                    'html' => $this->render('success', $result)
                ];
            }
        );
    }

    /**
     * @param string $processId
     * @return array
     * @throws Exception
     */
    protected function compareProcess($processId)
    {
        $comparator = $this->factory->getComparator();
        return $this->runProcess(
            $comparator,
            $processId,
            function($result) use ($processId, $comparator) {
                return $this->createCompareResult(
                    $processId,
                    $result,
                    $comparator->getInstalledRevision()
                );
            }
        );
    }

    /**
     * @param Processor $processor
     * @param $processId
     * @param $onEnd
     * @return array
     * @throws Exception
     */
    protected function runProcess($processor, $processId, $onEnd = null)
    {
        $start = microtime(true);
        $steps = 0;

        $limits = [
            Settings::PERFORMANCE_LOW => [ 'maxSteps' => 5, 'maxTime' => 10 ],
            Settings::PERFORMANCE_NORMAL => [ 'maxSteps' => 15, 'maxTime' => 15 ],
            Settings::PERFORMANCE_HIGH => [ 'maxSteps' => 30, 'maxTime' => 15 ]
        ];
        $maxSteps = $limits[Settings::getServerPerformance()]['maxSteps'];
        $maxTime = $limits[Settings::getServerPerformance()]['maxTime'];

        while(true) {
            $state = $processor->process($processId);
            $steps++;

            if ($state->hasFinished()) {
                $ret = [
                    'id' => $processId,
                    'status' => $state->getState()
                ];
                if ($state->hasFailed()) {
                    $ret['error'] = $state->getError();
                    $ret['details'] = $state->getDetails();
                    $ret['step'] = $processor->describeCurrentStep($processId);
                } else {
                    $ret['step'] = $this->l("Done");
                    $result = $processor->getResult($processId);
                    if (is_callable($onEnd)) {
                        $result = $onEnd($result);
                    }
                    $ret['result'] = $result;
                }
                return $ret;
            } else {
                $elapsedTime = microtime(true) - $start;
                if ($elapsedTime > $maxTime || $steps >= $maxSteps || $state->hasAjax()) {
                    return [
                        'id' => $processId,
                        'status' => $state->getState(),
                        'step' => $processor->describeCurrentStep($processId),
                        'progress' => $state->getProgress(),
                        'ajax' => $state->getAjax(),
                    ];
                }
            }
        }
        // should never happen
        throw new Exception("Invariant exception");
    }

    /**
     * @param string $compareProcessId
     * @param array $result
     * @param string $installedRevision
     * @return array
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function createCompareResult($compareProcessId, $result, $installedRevision)
    {
        $changeSet = $result['changeSet'];
        $targetRevision = $result['targetRevision'];
        $sameRevision = $targetRevision == $installedRevision;
        $changes = 0;
        $edits = 0;
        foreach ($changeSet as $arr) {
            foreach ($arr as $file => $mod) {
                if ($mod) {
                    $edits++;
                } else {
                    $changes++;
                }
            }
        }

        $stable = Settings::getUpdateMode() === Settings::UPDATE_MODE_STABLE;
        $versionType = $stable
            ? $this->l('stable')
            : $this->l('bleeding edge');

        $html = $this->render('result', [
            'compareProcessId' => $compareProcessId,
            'sameRevision' => $sameRevision,
            'edits' => $edits,
            'changes' => $changes,
            'versionType' => $versionType,
            'installedRevision' => $installedRevision,
            'targetRevision' => $targetRevision,
            'changeSet' => $changeSet
        ]);

        return [
            'html' => $html,
            'changeSet' => $changeSet
        ];
    }

    /**
     * @throws Exception
     */
    protected function initUpdateProcess()
    {
        $compareProcessId = Tools::getValue('compareProcessId');
        $comparator = $this->factory->getComparator();
        $result = $comparator->getResult($compareProcessId);
        if (! $result) {
            throw new Exception("Comparision result not found. Please reload the page and try again");
        }
        $targetFileList = $comparator->getFileList($compareProcessId, $result['targetRevision']);
        $updater = $this->factory->getUpdater();
        $processId = $updater->startProcess([
            'targetVersion' => $result['targetVersion'],
            'targetRevision' => $result['targetRevision'],
            'versionType' => $result['versionType'],
            'versionName' => $result['versionName'],
            'changeSet' => $result['changeSet'],
            'targetFileList' => $targetFileList
        ]);
        return [
            'id' => $processId,
            'status' => ProcessingState::IN_PROGRESS,
            'progress' => 0.0,
            'step' => $updater->describeCurrentStep($processId),
        ];
    }

    /**
     * Returns currently selected tab
     *
     * @return string
     */
    private function getActiveTab()
    {
        $tab = Tools::getValue(static::PARAM_TAB);
        return $tab ? $tab : static::TAB_UPDATE;
    }

    /**
     * Returns database differences
     *
     * @return array
     * @throws PrestaShopException
     * @throws ReflectionException
     */
    protected function getDatabaseDifferences()
    {
        require_once(__DIR__ . '/../../classes/schema/autoload.php');
        $logger = $this->factory->getLogger();
        $logger->log('Resolving database differences');

        $objectModelBuilder = new ObjectModelSchemaBuilder();
        $informationSchemaBuilder = new InformationSchemaBuilder();
        $comparator = new DatabaseSchemaComparator();
        $differences = $comparator->getDifferences($informationSchemaBuilder->getSchema(), $objectModelBuilder->getSchema());

        $differences = array_filter($differences, function(SchemaDifference $difference) {
            return $difference->getSeverity() !== SchemaDifference::SEVERITY_NOTICE;
        });
        usort($differences, function(SchemaDifference $diff1, SchemaDifference $diff2) {
            $ret = $diff2->getSeverity() - $diff1->getSeverity();
            if ($ret === 0) {
               $ret = (int)$diff1->isDestructive() - (int)$diff2->isDestructive();
            }
            return $ret;
        });

        $logger->log('Found ' . count($differences) . ' database differences');

        return array_map(function(SchemaDifference $difference) use ($logger) {
            $localId = str_replace('CoreUpdater\\', '', $difference->getUniqueId());
            $logger->log("  - " . $localId . ' - ' . $difference->describe());
            return [
                'id' => $difference->getUniqueId(),
                'description' => $difference->describe(),
                'severity' => $difference->getSeverity(),
                'destructive' =>$difference->isDestructive(),
            ];
        }, $differences);
    }

    /**
     * Fixes database schema differences
     *
     * @param array $ids unique differences ids to be fixed
     *
     * @return array new database differences (see getDatabaseDifferences method)
     * @throws PrestaShopException
     * @throws ReflectionException
     */
    private function applyDatabaseFix($ids)
    {
        require_once(__DIR__ . '/../../classes/schema/autoload.php');
        $logger = $this->factory->getLogger();
        $objectModelBuilder = new ObjectModelSchemaBuilder();
        $objectModelSchema = $objectModelBuilder->getSchema();
        foreach (static::getDBServers() as $server) {
            // we need to create connection from scratch, because DB::getInstance() doesn't provide mechanism to
            // retrieve connection to specific slave server
            $connection = new DbPDO($server['server'], $server['user'], $server['password'], $server['database']);
            $informationSchemaBuilder = new InformationSchemaBuilder($connection);
            $comparator = new DatabaseSchemaComparator();
            $differences = $comparator->getDifferences($informationSchemaBuilder->getSchema(), $objectModelSchema);
            $indexed = [];
            foreach ($differences as $diff) {
                $indexed[$diff->getUniqueId()] = $diff;
            }
            foreach ($ids as $id) {
                if (isset($indexed[$id])) {
                    $localId = str_replace('CoreUpdater\\', '', $id);
                    $logger->log('Applying fix for database difference ' . $localId);
                    /** @var SchemaDifference $diff */
                    $diff = $indexed[$id];
                    $diff->applyFix($connection);
                } else {
                    $logger->log('Failed to apply fix for database difference ' . $id . ': no such difference found');
                }
            }
        }
        return $this->getDatabaseDifferences();
    }

    /**
     * Returns list of all database servers (both master and slaves)
     *
     * @return array
     */
    private static function getDBServers()
    {
        // ensure slave server settings are loaded
        Db::getInstance(_PS_USE_SQL_SLAVE_);
        return Db::$_servers;
    }

    /**
     * Checks that module version is supported by API server
     * @return boolean
     * @throws SmartyException
     * @throws PrestaShopException
     */
    protected function checkModuleVersion()
    {
        $currentVersion = $this->module->version;
        $logger = $this->factory->getLogger();
        if (Settings::versionCheckNeeded($currentVersion)) {
            $logger->log('Checking if module version ' . $currentVersion . ' is supported');
            $result = $this->factory->getApi()->checkModuleVersion($currentVersion);

            if (! is_array($result) || !isset($result['supported']) || !isset($result['latest'])) {
                $this->content = $this->render('error', ['errorMessage' => 'Invalid check module version response']);
                $logger->error('Invalid check module version response');
                return false;
            }
            $supported = !!$result['supported'];
            $latestVersion = $result['latest'];
            if ($supported) {
                Settings::updateVersionCheck($currentVersion, $latestVersion, true);
                $logger->log('Module version is supported');
                return true;
            }

            $logger->error('Module version ' . $currentVersion . ' is NOT supported');
            Settings::updateVersionCheck($currentVersion, $latestVersion, false);
            $this->content .= $this->render('unsupported-version', [
                'currentVersion' => $currentVersion,
                'latestVersion' => $latestVersion,
            ]);
            return false;
        } else {
            $logger->log('Skipping module version check, last checked ' . Settings::getSecondsSinceLastCheck($currentVersion) . ' seconds ago');
        }

        return true;
    }

    /**
     * @param $tab
     * @return string
     * @throws PrestaShopException
     */
    public static function tabLink($tab)
    {
        return Context::getContext()->link->getAdminLink('AdminCoreUpdater') . '&' . static::PARAM_TAB . '=' . $tab;
    }

    /**
     * @param string $template
     * @param array $params
     * @return string
     * @throws SmartyException
     */
    protected function render($template, $params = [])
    {
        $this->context->smarty->assign($params);
        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'coreupdater/views/templates/admin/' . $template .'.tpl');
    }
}
