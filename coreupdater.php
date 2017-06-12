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
 * Class CoreUpdater
 */
class CoreUpdater extends Module
{
    /**
     * CoreUpdater constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->name = 'coreupdater';
        $this->tab = 'administration';
        $this->version = '0.9.0';
        $this->author = 'thirty bees';
        $this->bootstrap = true;
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Core Updater');
        $this->description = $this->l('This module brings the tools for keeping your shop installation up to date.');
        $this->tb_versions_compliancy = '>= 1.0.0';
    }

    /**
     * Install this module.
     *
     * @return bool Whether this module was successfully installed.
     *
     * @since 1.0.0
     */
    public function install()
    {
        return parent::install();
    }

    /**
     * Uninstall this module.
     *
     * @return bool Whether this module was successfully uninstalled.
     *
     * @since 1.0.0
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Get module configuration page.
     *
     * @return string Configuration page HTML.
     *
     * @since 1.0.0
     */
    public function getContent()
    {
        return '<p>Hello, world!</p>';
    }
}
