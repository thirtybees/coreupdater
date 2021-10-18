<?php
/**
 * Copyright (C) 2021 - 2021 thirty bees
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
 * @copyright 2021 - 2021 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

namespace CoreUpdater;


use CoreUpdater\Api\ThirtybeesApi;
use CoreUpdater\Api\ThirtybeesApiGuzzle;
use CoreUpdater\Log\Logger;
use CoreUpdater\Storage\StorageFactory;

require_once __DIR__.'/logger/Logger.php';
require_once __DIR__.'/api/ThirtybeesApi.php';
require_once __DIR__.'/api/ThirtybeesApiException.php';
require_once __DIR__.'/api/ThirtybeesApiGuzzle.php';
require_once __DIR__.'/process/Process.php';
require_once __DIR__.'/process/ProcessingState.php';
require_once __DIR__.'/process/Comparator.php';
require_once __DIR__.'/process/Updater.php';
require_once __DIR__.'/storage/Storage.php';
require_once __DIR__.'/storage/StorageFactory.php';
require_once __DIR__.'/storage/StorageDb.php';
require_once __DIR__.'/Utils.php';
require_once __DIR__.'/Settings.php';
require_once __DIR__.'/Retrocompatibility.php';
require_once __DIR__.'/CodeCallback.php';
require_once __DIR__.'/ErrorHandler.php';
require_once __DIR__.'/schema/autoload.php';

class Factory
{

    /**
     * @var string Base uri to thirty bees api server
     */
    private $baseApiUri;

    /**
     * @var string Base url to the server
     */
    private $baseUrl;
    /**
     * @var string Full path to thirty bees root directory, using linux slashes
     */
    private $rootDir;

    /**
     * @var string Name of admin directory
     */
    private $adminDir;

    /**
     * @var string Path to pem file containing trusted root certificate authorities
     */
    private $truststore;

    /**
     * @var ThirtybeesApi
     */
    private $api;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var StorageFactory
     */
    private $storageFactory;

    /**
     * @var Updater
     */
    private $updater;

    /**
     * @var Comparator
     */
    private $comparator;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * Factory constructor.
     *
     * @param string $baseApiUri Uri to base thirty bees api server, ie. https://api.thirtybees.com
     * @param string $baseUrl Base url to shop
     * @param string $rootDir Full path to thirty bees root directory
     * @param string $adminDir Full path to admin directory
     * @param string $truststore Path to pem file containing trusted root certificate authorities
     */
    public function __construct($baseApiUri, $baseUrl, $rootDir, $adminDir, $truststore)
    {
        $this->baseApiUri = rtrim($baseApiUri, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->rootDir = Utils::normalizeDirectory($rootDir);
        $this->adminDir = trim(str_replace($this->rootDir, '', Utils::normalizeDirectory($adminDir)), '/');
        $this->truststore = $truststore;
    }

    /**
     * @return ThirtybeesApi
     */
    public function getApi()
    {
        if (is_null($this->api)) {
            $this->api = new ThirtybeesApiGuzzle(
                $this->getLogger(),
                $this->baseApiUri,
                Settings::getApiToken(),
                $this->truststore,
                $this->rootDir,
                $this->adminDir,
                $this->getStorageFactory()
            );
        }
        return $this->api;
    }

    /**
     * @return StorageFactory
     */
    public function getStorageFactory()
    {
        if (is_null($this->storageFactory)) {
            $this->storageFactory = new StorageFactory(Utils::normalizeDirectory($this->rootDir . 'cache/'));
        }
        return $this->storageFactory;
    }

    /**
     * @return Updater
     */
    public function getUpdater()
    {
        if (is_null($this->updater)) {
            $this->updater = new Updater(
                $this->getLogger(),
                $this->getStorageFactory(),
                $this->getApi(),
                $this->adminDir,
                $this->rootDir,
                $this->baseUrl
            );
        }
        return $this->updater;
    }

    /**
     * @return Comparator
     */
    public function getComparator()
    {
        if (is_null($this->comparator)) {
            $this->comparator = new Comparator(
                $this->getLogger(),
                $this->getStorageFactory(),
                $this->getApi()
            );
        }
        return $this->comparator;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        if (is_null($this->logger)) {
            $file = $this->rootDir . 'log/coreupdater-' . date('Ymd') . '.log';
            $this->logger = new Logger($file);
        }
        return $this->logger;
    }

    /**
     * @return ErrorHandler
     */
    public function getErrorHandler()
    {
        if (is_null($this->errorHandler)) {
            $this->errorHandler = new ErrorHandler($this->getLogger());
        }
        return $this->errorHandler;
    }

}