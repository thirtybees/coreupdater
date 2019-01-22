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

        // $errors = array_merge($errors, $me->doSomeStep());

        return $errors;
    }
}
