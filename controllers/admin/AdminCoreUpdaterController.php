<?php
/**
 * Copyright (C) 2018-2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2018-2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'/coreupdater/classes/GitUpdate.php';

/**
 * Class AdminCoreUpdaterController.
 */
class AdminCoreUpdaterController extends ModuleAdminController
{
    const API_URL   = 'https://api.thirtybees.com/installationmaster.php';
    const CHANNELS  = [
        'Stable'                      => 'tags',
        'Bleeding Edge'               => 'branches',
        //'Developer (enter Git hash)'  => 'gitHash', // implementation postponed
    ];
    // For the translations parser:
    // $this->l('Stable');
    // $this->l('Bleeding Edge');
    // $this->l('Developer (enter Git hash)');

    /**
     * AdminCoreUpdaterController constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->bootstrap = true;

        // Take a shortcut for Ajax requests.
        if (Tools::getValue('ajax')) {
            $method = 'ajax'.ucfirst(Tools::getValue('action'));
            if (method_exists($this, $method)) {
                $this->{$method}();
            }

            // Should be unreached.
            die('Invalid request for Ajax action \''.$method.'()\'.');
        }

        $displayChannelList = [];
        foreach (static::CHANNELS as $channel => $path) {
            $displayChannelList[] = [
                'channel' => $path,
                'name'    => $this->l($channel),
            ];
        }

        $selectedVersion = Tools::getValue('CORE_UPDATER_VERSION');
        if ( ! $selectedVersion) {
            $selectedVersion = _TB_VERSION_;
        }

        $this->fields_options = [
            'updatepanel' => [
                'title'       => $this->l('Update'),
                'description' => '<p>'
                                 .$this->l('Here you can easily update your thirty bees installation and/or switch between thirty bees versions.')
                                 .'</p>',
                'info'        => $this->l('Current thirty bees version:')
                                 .' <b>'._TB_VERSION_.'</b>',
                'submit'      => [
                    'title'     => $this->l('Compare'),
                    'imgclass'  => 'refresh',
                    'name'      => 'coreUpdaterCompare',
                ],
                'fields' => [
                    'CORE_UPDATER_PARAMETERS' => [
                        'type'        => 'hidden',
                        'value'       => htmlentities(json_encode([
                            'apiUrl'          => static::API_URL,
                            'selectedVersion' => $selectedVersion,
                            'errorRetrieval'  => $this->l('Request failed, see JavaScript console.'),
                        ])),
                        'auto_value' => false,
                    ],
                    'CORE_UPDATER_CHANNEL' => [
                        'type'        => 'select',
                        'title'       => $this->l('Channel:'),
                        'desc'        => $this->l('This is the Git channel to update from. "Stable" lists releases, "Bleeding Edge" lists development branches.'),
                        'identifier'  => 'channel',
                        'list'        => $displayChannelList,
                    ],
                    'CORE_UPDATER_VERSION' => [
                        'type'        => 'select',
                        'title'       => $this->l('Version to compare to:'),
                        'desc'        => $this->l('Retrieving versions for this channel ...'),
                        'identifier'  => 'version',
                        'list'        => [
                            [
                                'version' => '',
                                'name'    => '',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (Tools::isSubmit('coreUpdaterCompare')) {
            /*
             * Show an empty file compare panel. Existence of this panel
             * causes JavaScript to trigger requests for doing all the steps
             * necessary for preparing an update, which also fills the lists.
             */
            $this->fields_options['comparepanel'] = [
                'title'       => $this->l('Update Comparison'),
                'description' => '<p>'
                                 .$this->l('This panel compares all files of this shop installation with a clean installation of the version given above. To update this shop to that version, update all files to the clean installation.')
                                 .'</p>',
                'submit'      => [
                    'title'     => $this->l('Update'),
                    'imgclass'  => 'update',
                    'name'      => 'coreUpdaterUpdate',
                ],
                'fields' => [
                    'CORE_UPDATER_PROCESSING' => [
                        'type'        => 'textarea',
                        'title'       => $this->l('Processing log:'),
                        'cols'        => 2000,
                        'rows'        => 3,
                        'value'       => $this->l('Starting...'),
                        'auto_value'  => false,
                    ],
                    'CORE_UPDATER_UPDATE' => [
                        'type'        => 'none',
                        'title'       => $this->l('Files to get changed:'),
                        'desc'        => $this->l('These files get updated for the version change.'),
                    ],
                    'CORE_UPDATER_ADD' => [
                        'type'        => 'none',
                        'title'       => $this->l('Files to get created:'),
                        'desc'        => $this->l('These files get created for the version change.'),
                    ],
                    'CORE_UPDATER_REMOVE' => [
                        'type'        => 'none',
                        'title'       => $this->l('Files to get removed:'),
                        'desc'        => $this->l('These files get removed for the version change.'),
                    ],
                    'CORE_UPDATER_REMOVE_OBSOLETE' => [
                        'type'        => 'none',
                        'title'       => $this->l('Obsolete files:'),
                        'desc'        => $this->l('These files exist locally, but are not needed for the selected version. Mark the checkbox(es) to remove them.'),
                    ],
                ],
            ];
        }

        parent::__construct();
    }

    /**
     * Get back office page HTML.
     *
     * @return string Page HTML.
     *
     * @since 1.0.0
     */
    public function initContent()
    {
        $this->page_header_toolbar_title = $this->l('Core Updater');

        parent::initContent();
    }

    /**
     * Set media.
     *
     * @since 1.0.0
     */
    public function setMedia()
    {
        parent::setMedia();

        $this->addJquery();
        $this->addJS(_PS_MODULE_DIR_.'coreupdater/views/js/controller.js');
    }

    /**
     * Process one step of a version comparison. The calling panel repeats this
     * request as long as 'done' returns false. Each call should not exceed
     * 3 seconds for a good user experience and a safe margin against the 30
     * seconds guaranteed maximum processing time.
     *
     * This function is expected to not return.
     *
     * @since 1.0.0
     */
    public function ajaxProcessCompare() {
        $messages = [
            'informations'  => [],
            'done'          => true,
        ];

        $version = Tools::getValue('compareVersion');
        if ( ! $version) {
            die('Parameter \'compareVersion\' is empty.');
        }

        $start = time();
        do {
            $stepStart = microtime(true);

            GitUpdate::compareStep($messages, $version);

            $messages['informations'][count($messages['informations']) - 1]
                .= sprintf(' (%.1f s)', microtime(true) - $stepStart);
        } while ($messages['done'] !== true && time() - $start < 3);

        die(json_encode($messages));
    }
}
