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
use PrestaShopException;
use ReflectionClass;

class Settings
{
    // setting keys
    const SETTINGS_UPDATE_MODE = 'CORE_UPDATER_UPDATE_MODE';
    const SETTINGS_SYNC_THEMES = 'CORE_UPDATER_SYNC_THEMES';
    const SETTINGS_SERVER_PERFORMANCE = 'CORE_UPDATER_SERVER_PERFORMANCE';
    const SETTINGS_VERSION_CHECK = 'CORE_UPDATER_VERSION_CHECK';
    const SETTINGS_LATEST_MODULE_VERSION = 'CORE_UPDATER_LATEST_MODULE_VERSION';
    const SETTINGS_API_TOKEN = 'CORE_UPDATER_TOKEN';

    // values
    const API_SERVER = 'https://api.thirtybees.com';

    const UPDATE_MODE_STABLE = "STABLE";
    const UPDATE_MODE_BLEEDING_EDGE = "BLEEDING_EDGE";

    const PERFORMANCE_LOW = 'LOW';
    const PERFORMANCE_NORMAL = 'NORMAL';
    const PERFORMANCE_HIGH = 'HIGH';


    /**
     * @return string
     */
    public static function getApiServer()
    {
        $value = Configuration::getGlobalValue('TB_API_SERVER_OVERRIDE');
        if ($value) {
            return $value;
        }
        return static::API_SERVER;
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public static function getUpdateMode()
    {
        $value = Configuration::getGlobalValue(static::SETTINGS_UPDATE_MODE);
        if (! $value) {
            $value = static::setUpdateMode(static::UPDATE_MODE_STABLE);
        }
        return $value;
    }

    /**
     * @return bool
     */
    public static function syncThemes()
    {
        return !!Configuration::getGlobalValue(static::SETTINGS_SYNC_THEMES);
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public static function getServerPerformance()
    {
        $value = Configuration::getGlobalValue(static::SETTINGS_SERVER_PERFORMANCE);
        if (! $value) {
            $value = static::setServerPerformance(static::PERFORMANCE_NORMAL);
        }
        return $value;
    }

    /**
     * @param boolean $sync
     * @return boolean
     * @throws PrestaShopException
     */
    public static function setSyncThemes($sync)
    {
        Configuration::updateGlobalValue(static::SETTINGS_SYNC_THEMES, $sync ? 1 : 0);
        return !!$sync;
    }

    /**
     * @param string $updateMode
     * @return string
     * @throws PrestaShopException
     */
    public static function setUpdateMode($updateMode)
    {
        if (! in_array($updateMode, [static::UPDATE_MODE_STABLE, static::UPDATE_MODE_BLEEDING_EDGE])) {
            $updateMode = static::UPDATE_MODE_STABLE;
        }
        Configuration::updateGlobalValue(static::SETTINGS_UPDATE_MODE, $updateMode);
        return $updateMode;
    }

    /**
     * Sets API token
     * @param string $token
     * @throws PrestaShopException
     */
    public static function setApiToken($token)
    {
        if ($token) {
            Configuration::updateGlobalValue(static::SETTINGS_API_TOKEN, $token);
        } else {
            Configuration::deleteByName(static::SETTINGS_API_TOKEN);
        }
    }

    /**
     * Returns API token
     *
     * @return string
     */
    public static function getApiToken()
    {
        return Configuration::getGlobalValue(static::SETTINGS_API_TOKEN);
    }

    /**
     * @param string $performance
     * @return string
     * @throws PrestaShopException
     */
    public static function setServerPerformance($performance)
    {
        if (! in_array($performance, [static::PERFORMANCE_HIGH, static::PERFORMANCE_LOW, static::PERFORMANCE_NORMAL])) {
            $performance = static::PERFORMANCE_NORMAL;
        }
        Configuration::updateGlobalValue(static::SETTINGS_SERVER_PERFORMANCE, $performance);
        return $performance;
    }

    /**
     * Returns latest module version
     * @return string
     */
    public static function getLatestModuleVersion()
    {
        $value = Configuration::getGlobalValue(static::SETTINGS_LATEST_MODULE_VERSION);
        if ($value) {
            return $value;
        }
        return '0.0.0';
    }

    /**
     * Return true, if module version should be checked
     * @param string $version
     * @return bool
     */
    public static function versionCheckNeeded($version)
    {
        return static::getSecondsSinceLastCheck($version) > (10 * 60);
    }

    /**
     * Returns number of seconds since last version check
     *
     * @param $version
     * @return int
     */
    public static function getSecondsSinceLastCheck($version)
    {
        $value = Configuration::getGlobalValue(static::SETTINGS_VERSION_CHECK);
        if ($value) {
            $split = explode('|', $value);
            if (is_array($split) && count($split) == 2) {
                if ($split[0] == $version) {
                    $now = time();
                    $ts = (int)$split[1];
                    return $now - $ts;
                }
            }
        }
        return PHP_INT_MAX;
    }

    /**
     * @param string $version
     * @param $latest
     * @param int $supported
     * @throws PrestaShopException
     */
    public static function updateVersionCheck($version, $latest, $supported)
    {
        Configuration::updateGlobalValue(static::SETTINGS_LATEST_MODULE_VERSION, $latest);
        if ($supported) {
            Configuration::updateGlobalValue(static::SETTINGS_VERSION_CHECK, $version . '|' . time());
        } else {
            Configuration::deleteByName(static::SETTINGS_VERSION_CHECK);
        }
    }

    /**
     * @return boolean
     * @throws PrestaShopException
     */
    public static function install()
    {
        static::setUpdateMode(self::UPDATE_MODE_STABLE);
        static::setSyncThemes(true);
        return true;
    }

    /**
     * Cleanup task
     * @return boolean
     */
    public static function cleanup()
    {
        try {
            $reflection = new ReflectionClass(__CLASS__);
            foreach ($reflection->getConstants() as $key => $configKey) {
                if (strpos($key, "SETTINGS_") === 0) {
                    Configuration::deleteByName($configKey);
                }
            }
        } catch (Exception $ignored) {}
        return true;
    }


}
