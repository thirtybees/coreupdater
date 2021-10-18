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

require_once __DIR__ . '/classes/Settings.php';

/**
 * Class CoreUpdater
 */
class CoreUpdater extends Module
{
    const MAIN_CONTROLLER = 'AdminCoreUpdater';

    /**
     * CoreUpdater constructor.
     *
     * @version 1.0.0 Initial version.
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'coreupdater';
        $this->tab = 'administration';
        $this->version = '1.4.0';
        $this->author = 'thirty bees';
        $this->bootstrap = true;
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Core Updater');
        $this->description = $this->l('This module brings the tools for keeping your shop installation up to date.');
        $this->tb_versions_compliancy = '>= 1.0.0';
        $this->tb_min_version = '1.0.0';
    }

    /**
     * @param bool $createTables
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    public function install($createTables = true)
    {
        Shop::setContext(Shop::CONTEXT_ALL);
        return (
            parent::install() &&
            CoreUpdater\Settings::install() &&
            $this->installDb($createTables) &&
            $tabSuccess = $this->installTab()
        );
    }

    /**
     * @param bool $dropTables
     * @return bool
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    public function uninstall($dropTables = true)
    {
        Shop::setContext(Shop::CONTEXT_ALL);
        return (
            $this->uninstallDb($dropTables) &&
            CoreUpdater\Settings::cleanup() &&
            $this->removeTab() &&
            parent::uninstall()
        );
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    public function reset()
    {
        return (
            $this->uninstall(false) &&
            $this->install(false)
        );
    }


    /**
     * Created databases tables
     *
     * @param boolean $create
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    private function installDb($create)
    {
        if (!$create) {
            return true;
        }
        return $this->executeSqlScript('install');
    }

    /**
     * Removes database tables
     *
     * @param boolean $drop
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    private function uninstallDb($drop)
    {
        if (!$drop) {
            return true;
        }
        return $this->executeSqlScript('uninstall', false);
    }

    /**
     * Executes sql script
     * @param string $script
     * @param bool $check
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    public function executeSqlScript($script, $check = true)
    {
        $file = dirname(__FILE__) . '/sql/' . $script . '.sql';
        if (!file_exists($file)) {
            return false;
        }
        $sql = file_get_contents($file);
        if (!$sql) {
            return false;
        }
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE', 'CHARSET_TYPE', 'COLLATE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_, 'utf8mb4', 'utf8mb4_unicode_ci'], $sql);
        $sql = preg_split("/;\s*[\r\n]+/", $sql);
        foreach ($sql as $statement) {
            $stmt = trim($statement);
            if ($stmt) {
                try {
                    if (!Db::getInstance()->execute($stmt)) {
                        PrestaShopLogger::addLog("coreupdater: sql script $script: $stmt: error");
                        if ($check) {
                            return false;
                        }
                    }
                } catch (\Exception $e) {
                    PrestaShopLogger::addLog("coreupdater: sql script $script: $stmt: exception: $e");
                    if ($check) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
    /**
     * Get module configuration page.
     *
     * Redirects to AdminCoreUpdaterController
     *
     * @throws PrestaShopException
     * @version 1.0.0 Initial version.
     */
    public function getContent()
    {
        require_once(__DIR__ . '/controllers/admin/AdminCoreUpdaterController.php');
        Tools::redirectAdmin(AdminCoreUpdaterController::tabLink(AdminCoreUpdaterController::TAB_SETTINGS));
    }

    /**
     * Adds menu item
     *
     * @return bool
     */
    private function installTab()
    {
        try {
            $tab = new Tab();
            $tab->module = $this->name;
            $tab->class_name = static::MAIN_CONTROLLER;
            $tab->id_parent = Tab::getIdFromClassName('AdminPreferences');
            foreach (Language::getLanguages() as $lang) {
                $tab->name[$lang['id_lang']] = $this->l('Core Updater');
            }

            return $tab->save();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Removes menu items
     *
     * @return boolean
     * @throws PrestaShopException
     */
    private function removeTab()
    {
        $tabs = Tab::getCollectionFromModule($this->name);
        foreach ($tabs as $tab) {
            if (! $tab->delete()) {
                return false;
            }
        }
        return true;
    }
}
