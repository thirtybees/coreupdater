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


interface ThirtybeesApi
{

    /**
     * @return string[]
     *
     * @throws ThirtybeesApiException
     */
    function getPHPVersions();

    /**
     * Returns all files tracked by given version. Output value is map from file name to md5 hash
     *
     * @param string $php
     * @param string $revision
     *
     * @return array
     *
     * @throws ThirtybeesApiException
     */
    function downloadFileList($php, $revision);

    /**
     * Returns version list
     *
     * @param string $php
     *
     * @return array
     *
     * @throws ThirtybeesApiException
     */
    public function getVersions($php);

    /**
     * Returns targets list
     *
     * @param string $php
     *
     * @return array
     *
     * @throws ThirtybeesApiException
     */
    public function getTargets($php);

    /**
     * Downloads files from revision $revision
     *
     * @param string $php
     * @param string $revision
     * @param string[] $files
     * @param string $targetFile
     *
     * @return void
     *
     * @throws ThirtybeesApiException
     */
    public function downloadFiles($php, $revision, $files, $targetFile);

    /**
     * Checks that module version is supported
     *
     * @param string $version
     *
     * @throws ThirtybeesApiException
     *
     * @return array
     */
    public function checkModuleVersion($version);

}