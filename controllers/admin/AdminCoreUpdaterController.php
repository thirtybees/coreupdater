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
    /**
     * AdminCoreUpdaterController constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->bootstrap = true;

        $this->fields_options = [
            'updatepanel' => [
                'title'       => $this->l('Update'),
                'description' => '<p>'
                                 .$this->l('Here you can easily update your thirty bees installation and/or switch between thirty bees versions.')
                                 .'</p>',
                'info'        => $this->l('Current thirty bees version:')
                                 .' <b>'._TB_VERSION_.'</b>',
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
}
