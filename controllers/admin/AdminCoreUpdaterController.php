<?php
/**
 * Copyright (C) 2018 thirty bees
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
 * @copyright 2018 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

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
}
