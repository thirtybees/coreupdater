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


use Db;
use DbQuery;
use PrestaShopDatabaseException;
use PrestaShopException;

class StorageDb implements Storage
{
    /**
     * @var string storage name
     */
    private $name;

    /**
     * @var int cache ttl
     */
    private $ttl;

    /**
     * @var array storage content
     */
    private $storage;

    /**
     * @var boolean
     */
    private $dirty;

    /**
     * StorageFile constructor.
     *
     * @param $name
     * @param int $ttl number of seconds this storage is active
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct($name, $ttl)
    {
        $this->name = $name;
        $this->ttl = $ttl;
        $this->storage = $this->load();
        $this->dirty = false;
    }

    /**
     * Stores information to database
     *
     * @throws PrestaShopException
     */
    public function save()
    {
        if ($this->dirty) {
            $name = pSQL($this->name);
            $content = pSQL(json_encode($this->storage));
            $expiration = (int)(time() + $this->ttl);

            $connection = Db::getInstance();
            $table = bqSQL(_DB_PREFIX_ . 'coreupdater_cache');
            $updateSql = (
                "INSERT INTO $table(name, content, expiration) " .
                "VALUES ('$name', '$content', $expiration) " .
                "ON DUPLICATE KEY UPDATE content=VALUES(content), expiration=VALUES(expiration)"
            );
            $connection->execute($updateSql);
            $this->dirty = false;
        }
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->storage);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function put($key, $value)
    {
        if (! $this->dirty) {
            if ($this->hasKey($key)) {
                if (is_string($value) || is_numeric($value) || is_bool($value)) {
                    $this->dirty = $value != $this->get($key);
                } else {
                    $this->dirty = true;
                }
            } else {
                $this->dirty = true;
            }
        }
        $this->storage[$key] = $value;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasKey($key)
    {
        return array_key_exists($key, $this->storage);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        if (array_key_exists($key, $this->storage)) {
            return $this->storage[$key];
        }
        return null;
    }


    /**
     * @return array
     */
    public function getAll()
    {
        return $this->storage;
    }

    /**
     * Loads storage from database
     *
     * @return array
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    private function load()
    {
        $connection = Db::getInstance();
        $connection->delete("coreupdater_cache", "expiration < " . time());
        $content = $connection->getValue((new DbQuery())
            ->select("content")
            ->from("coreupdater_cache")
            ->where("name = '" . pSQL($this->name) . "'")
        );
        if ($content) {
            $json = json_decode($content, true);
            if ($json) {
                return $json;
            }
        }
        return [];
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function flush()
    {
        $connection = Db::getInstance();
        $connection->delete("coreupdater_cache");
    }

}