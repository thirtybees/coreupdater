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
    const SETTINGS_INSTALLATION_VERIFIED = 'CORE_UPDATER_INSTALLATION_VERIFIED';
    const SETTINGS_CACHE_SYSTEM = 'CORE_UPDATER_CACHE_SYSTEM';
    const SETTINGS_VERIFY_SSL = 'CORE_UPDATER_VERIFY_SSL';
    const SETTINGS_TARGET_PHP_VERSION = 'CORE_UPDATER_TARGET_PHP_VERSION';
    const SETTINGS_DEVELOPER_MODE = 'CORE_UPDATER_DEVELOPER_MODE';

    // values
    const API_SERVER = 'https://api.thirtybees.com';

    const UPDATE_MODE_STABLE = "STABLE";
    const UPDATE_MODE_BLEEDING_EDGE = "BLEEDING_EDGE";
    const UPDATE_MODE_CUSTOM = "CUSTOM";

    const CACHE_DB = 'DB';
    const CACHE_FS = 'FS';

    const PERFORMANCE_LOW = 'LOW';
    const PERFORMANCE_NORMAL = 'NORMAL';
    const PERFORMANCE_HIGH = 'HIGH';

    const VERIFY_SSL_DISABLED = 'DISABLED';
    const VERIFY_SSL_SYSTEM = 'SYSTEM';
    const VERIFY_SSL_THIRTY_BEES = 'THIRTY_BEES';

    const CURRENT_PHP_VERSION = 'CURRENT';

    /**
     * @return string
     * @throws PrestaShopException
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
     * @return string
     * @throws PrestaShopException
     */
    public static function getCacheSystem()
    {
        $value = Configuration::getGlobalValue(static::SETTINGS_CACHE_SYSTEM);
        if (! in_array($value, [static::CACHE_DB, static::CACHE_FS])) {
            $value = static::setCacheSystem(static::CACHE_FS);
        }
        return $value;
    }

    /**
     * @param string $value
     *
     * @return string
     * @throws PrestaShopException
     */
    public static function setCacheSystem($value)
    {
        if (! in_array($value, [static::CACHE_DB, static::CACHE_FS])) {
            $value = static::CACHE_FS;
        }
        Configuration::updateGlobalValue(static::SETTINGS_CACHE_SYSTEM, $value);
        return $value;
    }

    /**
     * @return bool
     * @throws PrestaShopException
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
     * Return true, if installation is marked as verified
     *
     * @param string $version
     * @return bool
     * @throws PrestaShopException
     */
    public static function isInstallationVerified($version)
    {
        $value = Configuration::getGlobalValue(static::SETTINGS_INSTALLATION_VERIFIED);
        return $value === $version;
    }

    /**
     * @param string $version
     * @throws PrestaShopException
     */
    public static function setInstallationVerified($version)
    {
        Configuration::updateGlobalValue(static::SETTINGS_INSTALLATION_VERIFIED, $version);
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
        if (! in_array($updateMode, [
            static::UPDATE_MODE_STABLE,
            static::UPDATE_MODE_BLEEDING_EDGE,
            static::UPDATE_MODE_CUSTOM,
        ])) {
            $updateMode = static::UPDATE_MODE_STABLE;
        }
        Configuration::updateGlobalValue(static::SETTINGS_UPDATE_MODE, $updateMode);
        return $updateMode;
    }

    /**
     * Sets API token
     *
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
     * @throws PrestaShopException
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
     * @return string
     * @throws PrestaShopException
     */
    public static function getVerifySsl()
    {
        $value = Configuration::getGlobalValue(static::SETTINGS_VERIFY_SSL);
        if (! in_array($value, [static::VERIFY_SSL_DISABLED, static::VERIFY_SSL_SYSTEM, static::VERIFY_SSL_THIRTY_BEES])) {
            $value = static::setVerifySsl(static::VERIFY_SSL_THIRTY_BEES);
        }
        return $value;
    }

    /**
     * @param string $value
     * @return string
     * @throws PrestaShopException
     */
    public static function setVerifySsl($value)
    {
        if (! in_array($value, [static::VERIFY_SSL_DISABLED, static::VERIFY_SSL_SYSTEM, static::VERIFY_SSL_THIRTY_BEES])) {
            $value = static::VERIFY_SSL_THIRTY_BEES;
        }
        Configuration::updateGlobalValue(static::SETTINGS_VERIFY_SSL, $value);
        return $value;
    }

    /**
     * Returns latest module version
     * @return string
     * @throws PrestaShopException
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
     * @throws PrestaShopException
     */
    public static function versionCheckNeeded($version)
    {
        return static::getSecondsSinceLastCheck($version) > (10 * 60);
    }

    /**
     * Returns number of seconds since last version check
     *
     * @param string $version
     * @return int
     * @throws PrestaShopException
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
     * @param string $latest
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
        static::setUpdateMode(static::UPDATE_MODE_STABLE);
        static::setSyncThemes(true);
        static::setCacheSystem(static::CACHE_FS);
        static::setServerPerformance(static::PERFORMANCE_NORMAL);
        static::setVerifySsl(static::VERIFY_SSL_THIRTY_BEES);
        static::setTargetPHP(static::CURRENT_PHP_VERSION);
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

    /**
     * @param string $phpVersion
     * @return string
     * @throws PrestaShopException
     */
    public static function setTargetPHP($phpVersion)
    {
        Configuration::updateGlobalValue(static::SETTINGS_TARGET_PHP_VERSION, $phpVersion);
        return $phpVersion;
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public static function getTargetPHP()
    {
        $value = Configuration::getGlobalValue(static::SETTINGS_TARGET_PHP_VERSION);
        if (! $value) {
            $value = static::setTargetPHP(static::CURRENT_PHP_VERSION);
        }
        if ($value === static::CURRENT_PHP_VERSION) {
            return phpversion();
        }
        return $value;
    }

    /**
     * Sets developer mode flag
     *
     * @param bool $developerMode
     * @return boolean
     * @throws PrestaShopException
     */
    public static function setDeveloperMode($developerMode)
    {
        Configuration::updateGlobalValue(static::SETTINGS_DEVELOPER_MODE, $developerMode ? 1 : 0);
        return !!$developerMode;
    }

    /**
     * Returns true, if developer mode was enabled
     *
     * @return bool
     * @throws PrestaShopException
     */
    public static function isDeveloperMode()
    {
        return (bool)Configuration::getGlobalValue(static::SETTINGS_DEVELOPER_MODE);
    }

}
