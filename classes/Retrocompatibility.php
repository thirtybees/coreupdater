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

        return $errors;
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
}
