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

use CoreUpdater\Api\ThirtybeesApi;
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
    const TAB_DEVELOPER = 'developer';

    const ACTION_SAVE_SETTINGS = 'SAVE_SETTINGS';
    const ACTION_CLEAR_CACHE = 'CLEAR_CACHE';
    const ACTION_COMPARE_PROCESS = "COMPARE";
    const ACTION_INIT_UPDATE = "INIT_UPDATE";
    const ACTION_UPDATE_PROCESS = "UPDATE";
    const ACTION_GET_DATABASE_DIFFERENCES = "GET_DATABASE_DIFFERENCES";
    const ACTION_APPLY_DATABASE_FIX = 'APPLY_DATABASE_FIX';
    const ACTION_RUN_POST_UPDATE_PROCESSES = 'RUN_POST_UPDATE_PROCESSES';

    const SELECTED_PROCESS_MIGRATE_DB = 'SELECTED_PROCESS_MIGRATE_DB';
    const SELECTED_PROCESS_INITIALIZATION_CALLBACK = 'SELECTED_PROCESS_INITIALIZATION_CALLBACK';

    /**
     * @var Factory
     */
    private $factory;

    /**
     * AdminCoreUpdaterController constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $baseLink = Context::getContext()->shop->getBaseURI();
        $this->factory = new Factory(
            Settings::getApiServer(),
            $baseLink,
            _PS_ROOT_DIR_,
            _PS_ADMIN_DIR_,
            static::resolveTrustStore()
        );
        parent::__construct();
    }

    /**
     * Returns trust store for guzzle communication
     *
     * @return bool|string
     * @throws PrestaShopException
     */
    private static function resolveTrustStore()
    {
        switch (Settings::getVerifySsl()) {
            case Settings::VERIFY_SSL_DISABLED:
                return false;
            case Settings::VERIFY_SSL_SYSTEM:
                return true;
            case Settings::VERIFY_SSL_THIRTY_BEES:
                return _PS_TOOL_DIR_ . '/cacert.pem';
            default:
                return false;
        }
    }

    /**
     * @throws PrestaShopException
     * @throws SmartyException
     */
    private function initUpdateTab()
    {
        $updateMode = Settings::getUpdateMode();
        $php = Settings::getTargetPHP();
        try {
            $version = $this->findVersionToUpdate($php, $updateMode);
            if ($version) {
                $this->updateProcessView($updateMode, $version);
            } else {
                $this->selectTargetVersionView($php);
            }
        } catch (ThirtybeesApiException $e) {
            $this->content .= $this->render('error', [
                'errorMessage' => $this->l('Failed to connect to API server'),
                'errorDetails' => $e->getMessage()
            ]);
        }
    }

    /**
     * @return void
     * @throws PrestaShopException
     */
    private function initDeveloperTab()
    {
        if (Settings::isDeveloperMode()) {
            $this->fields_options = [
                'runPostUpdateProcesses' => [
                    'title'       => $this->l('Execute Post Update processes'),
                    'icon'        => 'icon-terminal',
                    'description' => (
                        $this->l('You can manually execute processes that are run after each update.') . '<br>' .
                        $this->l('This can be useful for testing changes in core database schema or code initialization.')
                    ),
                    'submit'      => [
                        'title'     => $this->l('Execute processes'),
                        'imgclass'  => 'save',
                        'name'      => static::ACTION_RUN_POST_UPDATE_PROCESSES,
                    ],
                    'fields' => [
                        static::SELECTED_PROCESS_MIGRATE_DB => [
                            'type'       => 'bool',
                            'title'      => $this->l('Migrate database'),
                            'desc'       => $this->l('Fixes some critical databas schema differencies. For example adds missing tables'),
                            'defaultValue' => true,
                            'no_multishop_checkbox' => true,
                        ],
                        static::SELECTED_PROCESS_INITIALIZATION_CALLBACK => [
                            'type'       => 'bool',
                            'title'      => $this->l('Initialize codebase'),
                            'desc'       => $this->l('Executes initialization method on all classes that implementes InitializationCallback interface'),
                            'defaultValue' => true,
                            'no_multishop_checkbox' => true,
                        ],
                    ],
                ],
            ];
        } else {
            $this->errors[] = $this->l('Developer mode is not enabled');
        }
    }

    /**
     * @param string $updateMode
     * @param array $version
     *
     * @return void
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    private function updateProcessView($updateMode, $version)
    {
        $comparator = $this->factory->getComparator();

        if (isset($version['type'])) {
            $versionName = $version['revision'];
            $versionType = $version['type'];
        } else {
            if ($version['stable']) {
                $versionName = $version['version'];
                $versionType = $this->l('stable');
            } else {
                $versionName = $version['revision'];
                $versionType = $this->l('bleeding edge');
            }
        }

        $employeeId = (int)$this->context->employee->id;

        $processId = $comparator->startProcess($employeeId, [
            'ignoreTheme' => !Settings::syncThemes(),
            'targetPHPVersion' => Settings::getTargetPHP(),
            'targetRevision' => $version['revision'],
            'targetVersion' => $version['version'],
            'versionName' => $versionName,
            'versionType' => $versionType,
        ]);

        $this->content .= $this->render('error');
        $this->content .= $this->render('tab_update', [
            'updateMode' => $updateMode,
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
     * @param string $php target php version
     *
     * @return void
     *
     * @throws PrestaShopException
     * @throws SmartyException
     * @throws ThirtybeesApiException
     */
    private function selectTargetVersionView($php)
    {
        $api = $this->factory->getApi();
        $this->content = $this->render('select_target_version', [
            'targets' => $api->getTargets($php)
        ]);
    }

    /**
     * @param string $php target php version
     * @param string $updateMode Update mode
     *
     * @return array
     * @throws PrestaShopException
     * @throws ThirtybeesApiException
     */
    private function findVersionToUpdate($php, $updateMode)
    {
        $api = $this->factory->getApi();
        $logger = $this->factory->getLogger();

        if (Tools::isSubmit('submitUpdate')) {
            $type = Tools::getValue('version_type');
            if ($type === 'release') {
                $selectedTarget = Tools::getValue('release');
                if ($selectedTarget) {
                    $targets = $api->getTargets($php);
                    foreach ($targets['releases'] as $version) {
                        if ($version['revision'] === $selectedTarget) {
                            $version['type'] = sprintf($this->l('release %s'), $version['name']);
                            return $version;
                        }
                    }
                }
            } else {
                $selectedTarget = Tools::getValue('branch');
                if ($selectedTarget) {
                    $targets = $api->getTargets($php);
                    foreach ($targets['branches'] as $version) {
                        if ($version['revision'] === $selectedTarget) {
                            $version['type'] = sprintf($this->l('branch %s'), $version['name']);
                            return $version;
                        }
                    }
                }
            }
        }

        if ($updateMode === Settings::UPDATE_MODE_CUSTOM) {
            return [];
        }

        $stable = $updateMode === Settings::UPDATE_MODE_STABLE;
        $logger->log("Resolving latest version for " . $updateMode);
        $versions = $api->getVersions($php);
        foreach ($versions as $version) {
            if ($version['stable'] === $stable) {
                $logger->log("Latest version = " . json_encode($version, JSON_PRETTY_PRINT));
                return $version;
            }
        }

        return [];
    }

    /**
     * Method to set up page for Settings  tab
     *
     * @throws PrestaShopException
     */
    private function initSettingsTab()
    {
        $api = $this->factory->getApi();
        $phpVersions = [[
            'key' => Settings::CURRENT_PHP_VERSION,
            'name' => $this->l('Server PHP version')
        ]];
        foreach ($this->getSupportedPHPVersions($api) as $phpVersion) {
            $phpVersions[] = [
                'key' => $phpVersion,
                'name' => 'PHP ' . $phpVersion
            ];
        }
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
                    .'<li><b>'.$this->l('Custom target').'</b>&nbsp;&mdash;&nbsp;'
                    .$this->l("You will be able to update to any official release version, or even development branch.")
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
                            [
                                'mode' => Settings::UPDATE_MODE_CUSTOM,
                                'name' => $this->l('Custom target')
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
                    Settings::SETTINGS_CACHE_SYSTEM => [
                        'type' => 'select',
                        'title' => $this->l('Caching system'),
                        'desc' => $this->l('Choose caching system. This may have impact on performance'),
                        'identifier'  => 'key',
                        'no_multishop_checkbox' => true,
                        'list' => [
                            [
                                'key' => Settings::CACHE_FS,
                                'name' => $this->l('Cache on filesystem')
                            ],
                            [
                                'key' => Settings::CACHE_DB,
                                'name' => $this->l('Cache in database')
                            ],
                        ]
                    ],
                    Settings::SETTINGS_VERIFY_SSL => [
                        'type' => 'select',
                        'title' => $this->l('Verify SSL certificates'),
                        'desc' => $this->l('Select if module should verify SSL certificate when communicating with api server'),
                        'hint' => $this->l('For security reasons SSL certificates should be always verified. Turn this option off only if both your system truststore and thirty bees truststore are outdated.'),
                        'identifier'  => 'key',
                        'no_multishop_checkbox' => true,
                        'list' => [
                            [
                                'key' => Settings::VERIFY_SSL_DISABLED,
                                'name' => $this->l('Disable SSL verification')
                            ],
                            [
                                'key' => Settings::VERIFY_SSL_SYSTEM,
                                'name' => $this->l('Verify SSL using operation system trust store')
                            ],
                            [
                                'key' => Settings::VERIFY_SSL_THIRTY_BEES,
                                'name' => $this->l('Verify SSL using thirty bees trust store')
                            ],
                        ]
                    ],
                    Settings::SETTINGS_TARGET_PHP_VERSION => [
                        'type' => 'select',
                        'title' => $this->l('Target PHP version'),
                        'identifier'  => 'key',
                        'no_multishop_checkbox' => true,
                        'list' => $phpVersions,
                        'desc' => $this->l('Thirty bees offers different distribution packages for different PHP versions. You should always use package designed for your PHP version, or for previous versions'),
                        'hint' => $this->l('For advanced users only. If unsure, use "Server PHP version"'),
                        'defaultValue' => Settings::getTargetPHP()
                    ],
                ]
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
                    Settings::SETTINGS_DEVELOPER_MODE => [
                        'type'       => 'bool',
                        'title'      => $this->l('Developer mode'),
                        'desc'       => $this->l('This will unlock features that useful for core developers'),
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
                    case static::TAB_UPDATE:
                        $this->initUpdateTab();
                        break;
                    case static::TAB_SETTINGS:
                        $this->initSettingsTab();
                        break;
                    case static::TAB_DB:
                        $this->initDatabaseTab();
                        break;
                    case static::TAB_DEVELOPER:
                        $this->initDeveloperTab();
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
     * @throws PrestaShopException
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
                $this->addDeveloperButton();
                $this->addDatabaseButton();
                $this->addSettingsButton();
                break;
            case static::TAB_DB:
                $this->addDeveloperButton();
                $this->addUpdateButton();
                $this->addSettingsButton();
                break;
            case static::TAB_SETTINGS:
                $this->addDeveloperButton();
                $this->addDatabaseButton();
                $this->addUpdateButton();
                break;
            case static::TAB_DEVELOPER:
                $this->addDatabaseButton();
                $this->addSettingsButton();
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
     * @throws PrestaShopException
     */
    private function addDeveloperButton()
    {
        if (Settings::isDeveloperMode()) {
            $this->page_header_toolbar_btn['developer'] = [
                'icon' => 'process-icon-terminal',
                'href' => static::tabLink(static::TAB_DEVELOPER),
                'desc' => $this->l('Developer'),
            ];
        }
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
            Settings::setDeveloperMode(Tools::getValue(Settings::SETTINGS_DEVELOPER_MODE));
            Settings::setCacheSystem(Tools::getValue(Settings::SETTINGS_CACHE_SYSTEM));
            Settings::setVerifySsl(Tools::getValue(Settings::SETTINGS_VERIFY_SSL));
            Settings::setTargetPHP(Tools::getValue(Settings::SETTINGS_TARGET_PHP_VERSION));
            $this->factory->getStorageFactory()->flush();
            $this->confirmations[] = $this->l('Settings saved');
            $this->setRedirectAfter(static::tabLink(static::TAB_SETTINGS));
            $this->redirect();
        }

        if (Tools::isSubmit(static::ACTION_CLEAR_CACHE)) {
            $this->factory->getStorageFactory()->flush();
            $this->confirmations[] = $this->l('Cache cleared');
            $this->setRedirectAfter(static::tabLink(static::TAB_SETTINGS));
            $this->redirect();
        }

        if (Tools::isSubmit(static::ACTION_RUN_POST_UPDATE_PROCESSES)) {
            $this->runPostUpdateProcesses();
            $this->setRedirectAfter(static::tabLink(static::TAB_DEVELOPER));
            $this->redirect();
        }
    }

    /**
     * @param string $action
     */
    protected function ajaxProcess($action)
    {
        $logger = $this->factory->getLogger();
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
     *
     * @return array
     *
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function processAction($action)
    {
        switch ($action) {
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
                throw new PrestaShopException("Invalid action: $action");
        }
    }

    /**
     * @param string $php target php version
     * @return array
     *
     * @throws PrestaShopException
     * @throws ThirtybeesApiException
     */
    protected function getVersions($php)
    {
        return $this->factory->getApi()->getVersions($php);
    }

    /**
     * @param string $processId
     *
     * @return array
     *
     * @throws PrestaShopException
     * @throws SmartyException
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
     *
     * @return array
     *
     * @throws PrestaShopException
     * @throws SmartyException
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
     * @param string $processId
     * @param callable|null $onEnd
     *
     * @return array
     *
     * @throws PrestaShopException
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
    }

    /**
     * @param string $compareProcessId
     * @param array $result
     * @param string $installedRevision
     *
     * @return array
     *
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
            foreach ($arr as $mod) {
                if ($mod) {
                    $edits++;
                } else {
                    $changes++;
                }
            }
        }

        $versionType = $this->getUpdateModeDescription(Settings::getUpdateMode());

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
     * @throws PrestaShopException
     */
    protected function initUpdateProcess()
    {
        $compareProcessId = Tools::getValue('compareProcessId');
        $comparator = $this->factory->getComparator();
        $result = $comparator->getResult($compareProcessId);
        if (! $result) {
            throw new PrestaShopException("Comparision result not found. Please reload the page and try again");
        }
        $targetFileList = $comparator->getFileList(
            $compareProcessId,
            $result['targetRevision'],
            $result['targetPHPVersion']
        );
        $employeeId = (int)$this->context->employee->id;
        $updater = $this->factory->getUpdater();
        $processId = $updater->startProcess($employeeId, [
            'targetPHPVersion' => $result['targetPHPVersion'],
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
     *
     * @throws PrestaShopException
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
     *
     * @throws PrestaShopException
     */
    private static function getDBServers()
    {
        // ensure slave server settings are loaded
        Db::getInstance(_PS_USE_SQL_SLAVE_);
        return Db::$_servers;
    }

    /**
     * Checks that module version is supported by API server
     *
     * @return boolean
     */
    protected function checkModuleVersion()
    {
        $logger = $this->factory->getLogger();
        try {
            $currentVersion = $this->module->version;
            if (Settings::versionCheckNeeded($currentVersion)) {
                $logger->log('Checking if module version ' . $currentVersion . ' is supported');
                $result = $this->factory->getApi()->checkModuleVersion($currentVersion);

                if (!is_array($result) || !isset($result['supported']) || !isset($result['latest'])) {
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
        } catch (Exception $e) {
            $logger->error("Failed to check module version: " . $e);
        }

        return true;
    }

    /**
     * @param string $tab
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

    /**
     * @return string
     */
    protected function getUpdateModeDescription($updateMode)
    {
        switch ($updateMode) {
            case Settings::UPDATE_MODE_STABLE:
                return $this->l('stable');
            case Settings::UPDATE_MODE_BLEEDING_EDGE:
                return $this->l('bleeding edge');
            case Settings::UPDATE_MODE_CUSTOM:
                return '';
            default:
                throw new RuntimeException('Invariant exception');
        }
    }

    /**
     * @param ThirtybeesApi $api
     *
     * @return array
     */
    protected function getSupportedPHPVersions($api)
    {
        try {
            return $api->getPHPVersions();
        } catch (ThirtybeesApiException $e) {
            $this->errors[] = $this->l('Failed to resolve supported PHP versions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    protected function runPostUpdateProcesses()
    {
        $migrateDb = (bool)Tools::getValue(static::SELECTED_PROCESS_MIGRATE_DB);
        $initializeCodeBase = (bool)Tools::getValue(static::SELECTED_PROCESS_INITIALIZATION_CALLBACK);

        if ($migrateDb || $initializeCodeBase) {
            $updater = $this->factory->getUpdater();
            if ($migrateDb) {
                try {
                    $updater->migrateDb();
                    $this->confirmations[] = '<p>' . $this->l('Database has been migrated') . '</p>';
                } catch (Exception $e) {
                    $this->errors[] = $this->l('Failed to migrate database') . "<pre>$e</pre>";
                }
            }

            if ($initializeCodeBase) {
                try {
                    $updater->initializeCodebase();
                    $this->confirmations[] = '<p>' . $this->l('Codebase has been initialized') . '</p>';
                } catch (Exception $e) {
                    $this->errors[] = $this->l('Failed to initialize codebase') . "<pre>$e</pre>";
                }
            }
        } else {
            $this->warnings[] = $this->l('No operation performed');
        }
    }

    /**
     * @return void
     * @throws PrestaShopException
     */
    public function init()
    {
        // auto-login functionality
        // if employee is not logged in but update process is running, we will auto-login employee
        if (! $this->isEmployeeLoggedIn()) {
            $employee = $this->getCurrentUpdateProcessEmployee();
            if ($employee) {
                // Update cookie
                $this->context->employee = $employee;
                $cookie = $this->context->cookie;
                $cookie->id_employee = $employee->id;
                $cookie->email = $employee->email;
                $cookie->profile = $employee->id_profile;
                $cookie->passwd = $employee->passwd;
                $cookie->remote_addr = (int) ip2long(Tools::getRemoteAddr());
                $cookie->last_activity = time();
                $cookie->write();
                $this->token = Tools::getAdminToken($this->controller_name.(int) $this->id.(int) $employee->id);
                Cache::clean('*');
            }
        }

        parent::init();
    }

    /**
     * Returns true, if employee is logged in
     *
     * @return bool
     * @throws PrestaShopException
     */
    protected function isEmployeeLoggedIn()
    {
        $employee = $this->context->employee;
        if (Validate::isLoadedObject($employee)) {
            return $employee->isLoggedBack();
        }
        return false;
    }

    /**
     * @return Employee|false
     *
     * @throws PrestaShopException
     */
    protected function getCurrentUpdateProcessEmployee()
    {
        if (! Tools::getValue('ajax')) {
            return false;
        }

        $action = (string)Tools::getValue('action');
        if ($action !== static::ACTION_UPDATE_PROCESS) {
            return false;
        }

        $processId = (string)Tools::getValue('processId');
        if (! $processId) {
            return false;
        }

        // resolve update process employee
        $updater = $this->factory->getUpdater();
        $employee = new Employee($updater->getEmployeeId($processId));
        if (Validate::isLoadedObject($employee)) {
            return $employee;
        }
        return false;
    }
}
