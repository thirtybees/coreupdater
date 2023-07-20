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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PrestaShopException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

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
    const ACTION_TARGETS = 'targets';
    const ACTION_LIST_PHP_VERSIONS = 'list-php-versions';

    const MINUTE = 60;
    const MINUTE_10 = 60 * 10;
    const HOUR = 60 * 60;
    const DAY = 24 * 60 * 60;
    const MONTH = 60 * 60 * 24 * 30;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Client
     */
    private $guzzle;

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
     * @param string $adminDir Full path to admin directory
     * @param StorageFactory $storageFactory
     */
    public function __construct(
        Logger $logger,
        $baseUri,
        $token,
        $truststore,
        $adminDir,
        StorageFactory $storageFactory
    ) {
        $this->logger = $logger;
        $this->guzzle = new Client([
            'base_uri'    => rtrim($baseUri, '/'),
            'verify'      => $truststore,
            'timeout'     => 20,
        ]);
        $this->adminDir = $adminDir;
        $this->token = $token;
        $this->storageFactory = $storageFactory;
    }

    /**
     * @return string
     */
    private static function getCurrentPHPVersion()
    {
        return phpversion();
    }

    /**
     * @param string $php
     * @param string $revision
     *
     * @return array
     *
     * @throws ThirtybeesApiException
     * @throws PrestaShopException
     */
    public function downloadFileList($php, $revision)
    {
        $cacheTtl = $this->isStableRelease($revision)
            ? static::MONTH
            : static::HOUR;
        $storage = $this->storageFactory->getStorage($this->getCacheFile('files-' . $revision, $php), $cacheTtl);
        if ($storage->isEmpty()) {
            $list = $this->callApi($php, static::ACTION_LIST_REVISION, ['revision' => $revision]);
            foreach ($list as $path => $hash) {
                $path = preg_replace('#^admin/#', $this->adminDir . '/', $path);
                $storage->put($path, $hash);
            }
            $storage->save();
        }
        return $storage->getAll();
    }

    /**
     * @param string $php
     * @return array
     *
     * @throws ThirtybeesApiException
     * @throws PrestaShopException
     */
    public function getVersions($php)
    {
        $this->logger->log("Resolving available versions");
        $cacheTtl = static::MINUTE_10;
        $storage = $this->storageFactory->getStorage($this->getCacheFile('versions', $php), $cacheTtl);
        if (! $storage->hasKey('versions')) {
            $this->logger->log("Version list not found in cache, calling api");
            $versions = $this->callApi($php, static::ACTION_VERSIONS);
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
     * Returns targets list
     *
     * @param string $php
     * @return array
     *
     * @throws ThirtybeesApiException
     * @throws PrestaShopException
     */
    public function getTargets($php)
    {
        $this->logger->log("Resolving available targets");
        $cacheTtl = static::MINUTE_10;
        $storage = $this->storageFactory->getStorage($this->getCacheFile('targets', $php), $cacheTtl);
        if (! $storage->hasKey('targets')) {
            $this->logger->log("Targets list not found in cache, calling api");
            $targets = $this->callApi($php, static::ACTION_TARGETS);
            $storage->put('targets', $targets);
            $storage->save();
        } else {
            $this->logger->log("Targets list found in cache");
        }
        $targets = $storage->get('targets');
        $this->logger->log("Available targets = " . json_encode($targets));
        return $targets;
    }


    /**
     * Download files
     *
     * @param string $revision
     * @param string[] $files
     * @param string $targetFile
     *
     * @return void
     *
     * @throws ThirtybeesApiException
     */
    public function downloadFiles($php, $revision, $files, $targetFile)
    {
        $request = [
            'action' => 'download-archive',
            'php' => $php,
            'revision' => $revision,
            'paths' => $files
        ];
        if ($this->token) {
            $request['token'] = $this->token;
        }
        try {
            $debugRequest = [
                'action' => 'download-archive',
                'php' => $php,
                'revision' => $revision,
                'paths' => count($files) . ' files'
            ];
            $this->logger->log("API request: " . json_encode($debugRequest));
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
        } catch (ThirtybeesApiException $e) {
            @unlink($targetFile);
            throw $e;
        } catch (GuzzleException $e ) {
            throw static::wrapException($e, $request, $targetFile);
        } catch (Exception $e) {
            throw static::wrapException($e, $request, $targetFile);
        }
    }

    /**
     * @param string $version
     *
     * @return array
     *
     * @throws ThirtybeesApiException
     */
    public function checkModuleVersion($version)
    {
        return $this->callApi(static::getCurrentPHPVersion(), 'check-module-version', [
            'version' => $version
        ]);
    }

    /**
     * @return string[]
     *
     * @throws PrestaShopException
     * @throws ThirtybeesApiException
     */
    function getPHPVersions()
    {
        $this->logger->log("Resolving available PHP versions");
        $cacheTtl = static::DAY;
        $storage = $this->storageFactory->getStorage('php-versions', $cacheTtl);
        if (! $storage->hasKey('versions')) {
            $this->logger->log("List of supported PHP versions not found in cache, calling api");
            $versions = $this->callApi(static::getCurrentPHPVersion(), static::ACTION_LIST_PHP_VERSIONS);
            $storage->put('versions', $versions);
            $storage->save();
        } else {
            $this->logger->log("List of supported PHP versions found in cache");
        }
        $versions = $storage->get('versions');
        $this->logger->log("Available PHP versions = " . json_encode($versions));
        return $versions;
    }


    /**
     * @param string $php php version
     * @param string $action action to perform
     * @param array $payload action payload
     *
     * @return array
     *
     * @throws ThirtybeesApiException
     */
    private function callApi($php, $action, $payload = [])
    {
        $request = array_merge($payload, [
            'action' => $action,
            'php' => $php
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
     *
     * @return ResponseInterface
     *
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
            throw static::wrapException($e, $request);
        } catch (GuzzleException $e) {
            throw static::wrapException($e, $request);
        }
    }


    /**
     * @param array $request
     * @param ResponseInterface $response
     *
     * @return array
     *
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
            if ($json['success']) {
                if (array_key_exists('data', $json)) {
                    $data = $json['data'];
                    $debugData = $body;
                    if (is_array($data) && count($data) > 15) {
                        $debugData = $json;
                        $debugData['data'] = 'Array with ' . count($data) . ' items';
                        $debugData = json_encode($debugData);
                    }
                    $this->logger->log("API response: " . $debugData);
                    return $data;
                } else {
                    $this->logger->log("API response: " . $body);
                    return [];
                }
            } else {
                $this->logger->log("API response: " . $body);
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
     * @return StreamInterface|null
     */
    private static function getBody($response)
    {
        if (method_exists($response, 'getBody')) {
            return $response->getBody();
        }
        return null;
    }

    /**
     * @param string $body
     * @return array|null
     */
    private static function parseBody($body)
    {
        if ($body) {
            $json = json_decode($body, true);
            if (is_array($json) && array_key_exists('success', $json)) {
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
    private function getCacheFile($name, $php)
    {
        $version = substr(str_replace(".", "", $php), 0, 2);
        return $name . "-php" . $version;
    }

    /**
     * Returns true, if $version is stable release
     *
     * @param string $version
     * @return false
     */
    private function isStableRelease($version)
    {
        return !!preg_match("#^[0-9]+\.[0-9]+\.[0-9]+$#", $version);
    }

    /**
     * @param Exception|GuzzleException $e
     * @param array $request
     * @param string|null $targetFile
     *
     * @return ThirtybeesApiException
     */
    protected static function wrapException($e, $request, $targetFile = null)
    {
        if ($targetFile && file_exists($targetFile)) {
            unlink($targetFile);
        }
        $message = $e->getMessage();
        if (! $message) {
            $message = 'Transport exception';
        }
        return new ThirtybeesApiException($message, $request, $e);
    }


}