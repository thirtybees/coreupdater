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

namespace CoreUpdater\Storage;


use PrestaShopDatabaseException;
use PrestaShopException;

class StorageFactory
{
    /**
     * @var string directory where to save storage files
     */
    private $directory;

    /**
     * StorageFactory constructor.
     *
     * @param string $directory
     */
    public function __construct($directory)
    {
        $this->directory = $directory;
    }


    /**
     * @param string $name
     * @param int $ttl number of seconds this storage is active
     * @return Storage
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getStorage($name, $ttl = 3600)
    {
        return new StorageDb($name, $ttl);
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function flush()
    {
        return StorageDb::flush();
    }

}