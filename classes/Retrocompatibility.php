<?php
/**
 * Copyright (C) 2019 thirty bees
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
 * @copyright 2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

namespace CoreUpdater;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class Retrocompatibility.
 *
 * This class provides old fashioned upgrades. Newer thirty bees versions
 * implement installationCheck() methods for classes in need of upgrades
 * instead.
 */
class Retrocompatibility
{
    /**
     * Master method to apply all database upgrades.
     *
     * @return array Empty array on success, array with error messages on
     *               failure.
     *
     * @since 1.0.0
     */
    public static function doAllDatabaseUpgrades() {
        $errors = [];
        $me = new Retrocompatibility;

        $errors = array_merge($errors, $me->doSqlUpgrades());
        $errors = array_merge($errors, $me->handleSingleLangConfigs());
        $errors = array_merge($errors, $me->handleMultiLangConfigs());

        return $errors;
    }

    /**
     * Get translation for a given text.
     *
     * @param string $string String to translate.
     *
     * @return string Translation.
     *
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
     */
    protected function handleSingleLangConfigs() {
        $errors = [];

        foreach ([
            'TB_MAIL_SUBJECT_TEMPLATE'  => '[{shop_name}] {subject}',
        ] as $key => $value) {
            $currentValue = \Configuration::get($key);
            if ( ! $currentValue) {
                $result = \Configuration::updateValue($key, $value);
                if ( ! $result) {
                    $errors[] = sprintf($this->l('Could not set default value for configuration "%s".', $key));
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
     * @since 1.0.0
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
            $currentValue = \Configuration::get($key);
            if ($currentValue) {
                $needsWrite = true;
                $value = $currentValue;
            }

            foreach (\Language::getIDs(false) as $idLang) {
                $currentValue = \Configuration::get($key, $idLang);
                if ($currentValue) {
                    $values[$idLang] = $currentValue;
                } else {
                    $needsWrite = true;
                    $values[$idLang] = $value;
                }
            }

            if ($needsWrite) {
                // Delete eventual single language value.
                \Configuration::deleteByName($key);

                // Write multi language values.
                $result = \Configuration::updateValue($key, $values);
                if ( ! $result) {
                    $errors[] = sprintf($this->l('Could not set default value for configuration "%s".', $key));
                }
            }
        }

        return $errors;
    }
}
