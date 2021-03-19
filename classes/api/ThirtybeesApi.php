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
     * Returns all files tracked by given version. Output value is map from file name to md5 hash
     *
     * @param string $revision
     * @return array
     * @throws ThirtybeesApiException
     */
    function downloadFileList($revision);

    /**
     * Returns version list
     *
     * @return array
     * @throws ThirtybeesApiException
     */
    public function getVersions();

    /**
     * Downloads files from revision $revision
     *
     * @param string $revision
     * @param string[] $files
     * @param string $targetFile
     * @return mixed
     */
    public function downloadFiles($revision, $files, $targetFile);

    /**
     * Checks that module version is supported
     *
     * @param string $version
     */
    public function checkModuleVersion($version);

}