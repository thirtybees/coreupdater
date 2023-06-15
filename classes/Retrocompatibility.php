<?php
/**
 * Copyright (C) 2019 thirty bees
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
 * @copyright 2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

namespace CoreUpdater;

use Configuration;
use Exception;
use Language;
use Module;
use PrestaShopException;
use Tab;

/**
 * Class Retrocompatibility.
 *
 * This class provides old fashioned upgrades. Newer thirty bees versions
 * implement installationCheck() methods for classes in need of upgrades
 * instead.
 */
class Retrocompatibility
{

    const ANY_VERSION = '*';

    /**
     * Modules known to be incompatible starting at a certain version. If the
     * target version of the update is this or higher, these modules get
     * uninstalled.
     */
    const INCOMPATIBILE_MODULES = [
        '1.0.4'   => [
            'graphnvd3' => self::ANY_VERSION,
            'gridhtml' => self::ANY_VERSION,
            'pagesnotfound' => self::ANY_VERSION,
            'sekeywords' => self::ANY_VERSION,
            'statsbestcategories' => self::ANY_VERSION,
            'statsbestcustomers' => self::ANY_VERSION,
            'statsbestmanufacturers' => self::ANY_VERSION,
            'statsbestproducts' => self::ANY_VERSION,
            'statsbestsuppliers' => self::ANY_VERSION,
            'statsbestvouchers' => self::ANY_VERSION,
            'statscarrier' => self::ANY_VERSION,
            'statscatalog' => self::ANY_VERSION,
            'statscheckup' => self::ANY_VERSION,
            'statsequipment' => self::ANY_VERSION,
            'statsforecast' => self::ANY_VERSION,
            'statslive' => self::ANY_VERSION,
            'statsnewsletter' => self::ANY_VERSION,
            'statsorigin' => self::ANY_VERSION,
            'statspersonalinfos' => self::ANY_VERSION,
            'statsproduct' => self::ANY_VERSION,
            'statsregistrations' => self::ANY_VERSION,
            'statssales' => self::ANY_VERSION,
            'statssearch' => self::ANY_VERSION,
            'statsstock' => self::ANY_VERSION,
            'statsvisits' => self::ANY_VERSION,
        ],
        '1.5.0'   => [
            'tbupdater' => self::ANY_VERSION,
            'collectlogs' => '1.2.1', // collectlogs must have at least version 1.2.1
        ],
    ];

    /**
     * Master method to apply all database upgrades.
     *
     * @return array Empty array on success, array with error messages on
     *               failure.
     *
     * @throws PrestaShopException
     * @version 1.0.0 Initial version.
     */
    public static function doAllDatabaseUpgrades() {
        $errors = [];
        $me = new Retrocompatibility;

        $errors = array_merge($errors, $me->doSqlUpgrades());
        $errors = array_merge($errors, $me->handleSingleLangConfigs());
        $errors = array_merge($errors, $me->handleMultiLangConfigs());
        $errors = array_merge($errors, $me->deleteObsoleteTabs());
        return array_merge($errors, $me->addMissingTabs());
    }

    /**
     * Get translation for a given text.
     *
     * @param string $string String to translate.
     *
     * @return string Translation.
     *
     * @version 1.0.0 Initial version.
     */
    protected function l($string)
    {
        return \Translate::getModuleTranslation('coreupdater', $string,
                                                'coreupdater');
    }

    /**
     * Apply database upgrade scripts.
     *
     * @return array Empty array on success, array with error messages on
     *               failure.
     *
     * @version 1.0.0 Initial version.
     * @throws PrestaShopException
     */
    protected function doSqlUpgrades() {
        $errors = [];

        $upgrades = file_get_contents(__DIR__.'/retroUpgrades.sql');
        // Strip comments.
        $upgrades = preg_replace('#/\*.*?\*/#s', '', $upgrades);
        $upgrades = explode(';', $upgrades);

        $db = \Db::getInstance(_PS_USE_SQL_SLAVE_);
        $engine = (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB');
        foreach ($upgrades as $upgrade) {
            $upgrade = trim($upgrade);
            if (strlen($upgrade)) {
                $upgrade = str_replace(['PREFIX_', 'ENGINE_TYPE'],
                                       [_DB_PREFIX_, $engine], $upgrade);

                $result = $db->execute($upgrade);
                if ( ! $result) {
                    $errors[] = (trim($db->getMsgError()));
                }
            }
        }

        return $errors;
    }

    /**
     * Handle single language configuration values, like creating them as
     * necessary. With the old method, insertions were done by SQL directly,
     * and were also known to be troublesome (failed insertion, double
     * insertion, whatever).
     *
     * @return array Empty array on success, array with error messages on
     *               failure.
     *
     * @throws PrestaShopException
     * @version 1.0.0 Initial version.
     */
    protected function handleSingleLangConfigs() {
        $errors = [];

        foreach ([
            'TB_MAIL_SUBJECT_TEMPLATE'  => '[{shop_name}] {subject}',
        ] as $key => $value) {
            $currentValue = Configuration::get($key);
            if ( ! $currentValue) {
                $result = Configuration::updateValue($key, $value);
                if ( ! $result) {
                    $errors[] = sprintf($this->l('Could not set default value for configuration "%s".'), $key);
                }
            }
        }

        return $errors;
    }

    /**
     * Handle multiple language configuration values, like creating them as
     * necessary. This never really worked with the old method. Also do single
     * language -> multi language conversions, which were formerly done by PHP
     * scripts.
     *
     * @return array Empty array on success, array with error messages on
     *               failure.
     *
     * @throws PrestaShopException
     * @version 1.0.0 Initial version.
     */
    protected function handleMultiLangConfigs() {
        $errors = [];

        foreach ([
            'PS_ROUTE_product_rule'       => '{categories:/}{rewrite}',
            'PS_ROUTE_category_rule'      => '{rewrite}',
            'PS_ROUTE_layered_rule'       => '{categories:/}{rewrite}{/:selected_filters}',
            'PS_ROUTE_supplier_rule'      => '{rewrite}',
            'PS_ROUTE_manufacturer_rule'  => '{rewrite}',
            'PS_ROUTE_cms_rule'           => 'info/{categories:/}{rewrite}',
            'PS_ROUTE_cms_category_rule'  => 'info/{categories:/}{rewrite}',
        ] as $key => $value) {
            $values = [];
            $needsWrite = false;

            // If there is a single language value already, use this.
            $currentValue = Configuration::get($key);
            if ($currentValue) {
                $needsWrite = true;
                $value = $currentValue;
            }

            foreach (Language::getIDs(false) as $idLang) {
                $currentValue = Configuration::get($key, $idLang);
                if ($currentValue) {
                    $values[$idLang] = $currentValue;
                } else {
                    $needsWrite = true;
                    $values[$idLang] = $value;
                }
            }

            if ($needsWrite) {
                // Delete eventual single language value.
                Configuration::deleteByName($key);

                // Write multi language values.
                $result = Configuration::updateValue($key, $values);
                if ( ! $result) {
                    $errors[] = sprintf($this->l('Could not set default value for configuration "%s".'), $key);
                }
            }
        }

        return $errors;
    }

    /**
     * Delete obsolete back office menu items (tabs), which were forgotten to
     * get removed by earlier migration module versions. This was formerly
     * part of the 1.0.8 update, but applies to all versions.
     *
     * @return array Empty array on success, array with error messages on
     *               failure.
     *
     * @throws PrestaShopException
     * @version 1.0.0 Initial version.
     */
    protected function deleteObsoleteTabs() {
        $errors = [];

        foreach ([
            'AdminMarketing',
        ] as $tabClassName) {
            while ($idTab = Tab::getIdFromClassName($tabClassName)) {
                $result = (new Tab($idTab))->delete();
                if ( ! $result) {
                    $errors[] = sprintf($this->l('Could delete back office menu item for controller "%s".'), $tabClassName);
                }
            }
        }

        return $errors;
    }

    /**
     * Add missing back office menu items (tabs), which were forgotten to get
     * added by earlier migration module versions. This includes adjustment of
     * its position. This step was formerly part of the 1.0.8 update, but
     * applies to all versions.
     *
     * @return array Empty array on success, array with error messages on
     *               failure.
     *
     * @throws PrestaShopException
     * @version 1.0.0 Initial version.
     */
    protected function addMissingTabs() {
        $errors = [];

        foreach ([
            [
                'tabClassName'    => 'AdminDuplicateUrls',
                'tabName'         => 'Duplicate URLs',
                'parentClassName' => 'AdminParentPreferences',
                'aboveClassName'  => 'AdminMeta',
            ],
            [
                'tabClassName'    => 'AdminCustomCode',
                'tabName'         => 'Custom Code',
                'parentClassName' => 'AdminParentPreferences',
                'aboveClassName'  => 'AdminGeolocation',
            ],
            [
                'tabClassName'    => 'AdminAddonsCatalog',
                'tabName'         => 'Modules & Themes Catalog',
                'parentClassName' => 'AdminParentModules',
                'aboveClassName'  => 'AdminModules',
            ],
        ] as $tabSet) {
            if (Tab::getIdFromClassName($tabSet['tabClassName'])) {
                continue;
            }

            try {
                $tab = new Tab();

                $tab->class_name  = $tabSet['tabClassName'];
                if ($tabSet['parentClassName']
                    && $idParent = Tab::getIdFromClassName($tabSet['parentClassName'])) {
                    $tab->id_parent = $idParent;
                }

                if ($tabSet['tabName']) {
                    $langs = Language::getLanguages();
                    foreach ($langs as $lang) {
                        $translation = \Translate::getAdminTranslation(
                            $tabSet['tabName'], 'AdminTab', false, false);
                        $tab->name[$lang['id_lang']] = $translation;
                    }
                }

                $tab->save();
            } catch (Exception $e) {
                $errors[] = sprintf($this->l('Could not create back office menu item for class "%s".'), $tabSet['tabClassName']);
                continue;
            }

            // Move the new tab to just under the tab with class
            // $tabSet['aboveClassName'].
            if ($tabSet['aboveClassName']) {
                $tabList = Tab::getTabs(0, $tab->id_parent);

                // Find positions of relevant tabs.
                $posMe = false;
                $posAbove = false;
                foreach ($tabList as $item) {
                    if ($item['class_name'] === $tabSet['tabClassName']) {
                        $posMe = $item['position'];
                    } elseif ($item['class_name'] === $tabSet['aboveClassName']) {
                        $posAbove = $item['position'];
                    }
                }

                // Move. Failures not worth to disturb the merchant with.
                if ($posMe !== false && $posAbove !== false) {
                    $tab->updatePosition($posMe < $posAbove, $posAbove + 1);
                }
            }
        }

        return $errors;
    }

    /**
     * Get a list of installed modules incompatible with the target version.
     * No failure expected.
     *
     * Note: these modules should get uninstalled and deleted _before_ the
     *       update.
     *
     * @param string $targetVersion Target version.
     *
     * @return array Array with strings of errors
     *
     * @throws PrestaShopException
     * @version 1.0.0 Initial version.
     */
    public static function getIncompatibleModules($targetVersion)
    {
        $installedModules = static::getModulesInstalled();

        $incompatibles = [];
        foreach (static::INCOMPATIBILE_MODULES as $version => $list) {
            if (version_compare($targetVersion, $version, '>=')) {
                foreach ($list as $moduleName => $minVersion) {
                    if (isset($installedModules[$moduleName])) {
                        $installedVersion = $installedModules[$moduleName];
                        $reason = static::isModuleVersionIncompatibile($moduleName, $installedVersion, $minVersion);
                        if ($reason) {
                            $incompatibles[] = $reason;
                        }
                    }
                }
            }
        }
        return $incompatibles;
    }

    /**
     * @return array
     *
     * @throws PrestaShopException
     */
    public static function getModulesInstalled()
    {
        $modules = [];
        foreach (Module::getModulesInstalled() as $row) {
            $moduleName = $row['name'];
            $path = rtrim(_PS_ROOT_DIR_, '/\\') . '/modules/' . $moduleName . '/' . $moduleName . '.php';
            if (file_exists($path)) {
                $modules[$moduleName] = $row['version'];
            }
        }
        return $modules;
    }

    /**
     * @param string $installedVersion
     * @param string $minVersion
     *
     * @return string|null
     */
    protected static function isModuleVersionIncompatibile($moduleName, $installedVersion, $minVersion)
    {
        if ($minVersion === static::ANY_VERSION) {
            return sprintf("Module '%s' [%s] is not compatible with target version of thirty bees", static::getModuleName($moduleName), $moduleName);
        }

        if (version_compare($installedVersion, $minVersion, '<')) {
            return sprintf(
                "Module '%s' [%s] has version %s, at least %s is required",
                static::getModuleName($moduleName),
                $moduleName,
                $installedVersion,
                $minVersion
            );
        }

        // module is compatible
        return null;
    }

    /**
     * @return string
     */
    protected static function getModuleName($module)
    {
        try {
            return Module::getModuleName($module);
        } catch (Exception $e) {
           return $module;
        }
    }
}
