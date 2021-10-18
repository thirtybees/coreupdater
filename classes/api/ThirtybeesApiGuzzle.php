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

namespace CoreUpdater\Api;

use CoreUpdater\Log\Logger;
use CoreUpdater\Storage\StorageFactory;
use Exception;
use PrestaShopDatabaseException;
use PrestaShopException;
use Psr\Http\Message\ResponseInterface;

class ThirtybeesApiGuzzle implements ThirtybeesApi
{
    /**
     * Path to core updater service
     */
    const CORE_UPDATER_PATH = "/coreupdater/v2.php";

    /**
     * Actions supported by thirty bees API server
     */
    const ACTION_LIST_REVISION = 'list-revision';
    const ACTION_VERSIONS = 'versions';

    const MINUTE = 60;
    const MINUTE_10 = 60 * 10;
    const HOUR = 60 * 60;
    const MONTH = 60 * 60 * 24 * 30;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var GuzzleHttp
     */
    private $guzzle;

    /**
     * @var string Full path to thirty bees root directory, using linux slashes
     */
    private $rootDir;

    /**
     * @var string Name of admin directory
     */
    private $adminDir;

    /**
     * @var StorageFactory
     */
    private $storageFactory;

    /**
     * @var string
     */
    private $token;

    /**
     * ThirtybeesApiGuzzle constructor.
     *
     * @param Logger $logger
     * @param string $baseUri Uri to thirty bees API server, such as https://api.thirtybees.com
     * @param string $token API token to be used for communication with API server
     * @param string $truststore Path to pem file containing trusted root certificate authorities
     * @param string $rootDir Full path to thirty bees root directory
     * @param string $adminDir Full path to admin directory
     * @param StorageFactory $storageFactory
     */
    public function __construct(
        Logger $logger,
        $baseUri,
        $token,
        $truststore,
        $rootDir,
        $adminDir,
        StorageFactory $storageFactory
    ) {
        $this->logger = $logger;
        $this->guzzle = new \GuzzleHttp\Client([
            'base_uri'    => rtrim($baseUri, '/'),
            'verify'      => $truststore,
            'timeout'     => 20,
        ]);
        $this->rootDir = $rootDir;
        $this->adminDir = $adminDir;
        $this->token = $token;
        $this->storageFactory = $storageFactory;
    }

    /**
     * @param string $revision
     * @return array
     * @throws ThirtybeesApiException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function downloadFileList($revision)
    {
        $cacheTtl = $this->isStableRelease($revision)
            ? static::MONTH
            : static::HOUR;
        $storage = $this->storageFactory->getStorage($this->getCacheFile('files-' . $revision), $cacheTtl);
        if ($storage->isEmpty()) {
            $list = $this->callApi(static::ACTION_LIST_REVISION, ['revision' => $revision]);
            foreach ($list as $path => $hash) {
                $path = preg_replace('#^admin/#', $this->adminDir . '/', $path);
                $storage->put($path, $hash);
            }
            $storage->save();
        }
        return $storage->getAll();
    }

    /**
     * @return array
     * @throws ThirtybeesApiException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getVersions()
    {
        $this->logger->log("Resolving available versions");
        $cacheTtl = static::MINUTE_10;
        $storage = $this->storageFactory->getStorage($this->getCacheFile('versions'), $cacheTtl);
        if (! $storage->hasKey('versions')) {
            $this->logger->log("Version list not found in cache, calling api");
            $versions = $this->callApi(static::ACTION_VERSIONS);
            $storage->put('versions', $versions);
            $storage->save();
        } else {
            $this->logger->log("Version list found in cache");
        }
        $versions = $storage->get('versions');
        $this->logger->log("Available versions = " . json_encode($versions));
        return $versions;
    }

    /**
     * Download files
     * @param string $revision
     * @param string[] $files
     * @param string $targetFile
     * @return boolean
     * @throws ThirtybeesApiException
     */
    public function downloadFiles($revision, $files, $targetFile)
    {
        $request = [
            'action' => 'download-archive',
            'php' => phpversion(),
            'revision' => $revision,
            'paths' => $files
        ];
        if ($this->token) {
            $request['token'] = $this->token;
        }
        try {
            $this->logger->log("API request: " . json_encode($request));
            $this->guzzle->post(static::CORE_UPDATER_PATH, [
                'form_params' => $request,
                'http_errors' => false,
                'sink' => $targetFile
            ]);
            if (!is_file($targetFile)) {
                $this->logger->error("Failed to download files from server");
                throw new ThirtybeesApiException("File not created", $request);
            }
            $magicNumber = file_get_contents($targetFile, false, null, 0, 2);
            if (@filesize($targetFile) < 100 || strcmp($magicNumber, "\x1f\x8b")) {
                // It's an error message response.
                $message = file_get_contents($targetFile);
                $this->logger->error("Server responded with error message: " . $message);
                throw new ThirtybeesApiException($message, $request);
            }
            $this->logger->log("Downloaded file " . $targetFile);
            return true;
        } catch (ThirtybeesApiException $e) {
            @unlink($targetFile);
            throw $e;
        } catch (Exception $e) {
            @unlink($targetFile);
            $this->logger->error("Transport exception: " . $e->getMessage());
            throw new ThirtybeesApiException('Transport exception', $request, $e);
        }
    }

    /**
     * @param string $version
     *
     * @return mixed
     * @throws ThirtybeesApiException
     */
    public function checkModuleVersion($version)
    {
        return $this->callApi('check-module-version', [
            'version' => $version
        ]);
    }


    /**
     * @param String $action action to perform
     * @param array $payload action payload
     *
     * @return mixed
     * @throws ThirtybeesApiException
     */
    private function callApi($action, $payload = [])
    {
        $request = array_merge($payload, [
            'action' => $action,
            'php' => phpversion()
        ]);
        if ($this->token) {
            $request['token'] = $this->token;
        }

        $this->logger->log("API request: " . json_encode($request));

        $response = $this->performPost($request);
        return $this->unwrapResponse($request, $response);
    }

    /**
     * @param array $request
     * @return ResponseInterface
     * @throws ThirtybeesApiException
     */
    private function performPost($request)
    {
        try {
            return $this->guzzle->post(static::CORE_UPDATER_PATH, [
                'form_params' => $request,
                'http_errors' => false
            ]);
        } catch (Exception $e) {
            throw new ThirtybeesApiException('Transport exception', $request, $e);
        }
    }


    /**
     * @param $request
     * @param ResponseInterface $response
     * @return mixed
     * @throws ThirtybeesApiException
     */
    private function unwrapResponse($request, $response)
    {
        if (is_null($response))  {
            $this->logger->error("Response is null");
            throw new ThirtybeesApiException("Response is null", $request);
        }

        $body = static::getBody($response);
        $json = static::parseBody($body);
        if ($json) {
            $this->logger->log("API response: " . $body);
            if ($json['success']) {
                if (array_key_exists('data', $json)) {
                    return $json['data'];
                } else {
                    return true;
                }
            } else {
                if (array_key_exists('error', $json)) {
                    $error = $json['error'];
                    $this->logger->error("Server responded with error " . $error['code'] . ": " . $error['message']);
                    throw new ThirtybeesApiException("Server responded with error " . $error['code'] . ": " . $error['message'], $request);
                } else {
                    $this->logger->error("Server responded with unknown error");
                    throw new ThirtybeesApiException("Server responded with unknown error", $request);
                }
            }
        }
        $this->logger->error("Server returned unexpected response: $body");
        throw new ThirtybeesApiException("Server returned unexpected response: $body", $request);
    }

    /**
     * @param ResponseInterface $response
     * @return string | null
     */
    private static function getBody($response)
    {
        if (method_exists($response, 'getBody')) {
            $body = $response->getBody();
            if ($body) {
                return $body;
            }
        }
        return null;
    }

    /**
     * @param string $body
     * @return array | null
     */
    private static function parseBody($body)
    {
        if ($body) {
            $json = json_decode($body, true);
            if ($json && array_key_exists('success', $json)) {
                return $json;
            }
        }
        return null;
    }

    /**
     * Returns name of cache file
     *
     * @param string $name
     * @return string
     */
    private function getCacheFile($name)
    {
        $version = substr(str_replace(".", "", phpversion()), 0, 2);
        return $name . "-php" . $version;
    }

    /**
     * Returns true, if $version is stable release
     *
     * @param $version
     * @return false
     */
    private function isStableRelease($version)
    {
        return !!preg_match("#^[0-9]+\.[0-9]+\.[0-9]+$#", $version);
    }

}