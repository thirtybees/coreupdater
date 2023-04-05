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


class StorageFilesystem implements Storage
{
    /**
     * @var string storage name
     */
    private $filename;

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
     * @param string $directory
     * @param string $name
     * @param int $ttl number of seconds this storage is active
     */
    public function __construct($directory, $name, $ttl)
    {
        $index = $this->processIndex($directory);
        $this->filename = $directory . $name . '.json';
        $this->ttl = $ttl;
        if (isset($index[$name])) {
            $this->storage = $this->load();
        } else {
            $index[$name] = time() + $ttl;
            file_put_contents($directory . 'index.json', json_encode($index, JSON_PRETTY_PRINT));
            $this->storage = [];
        }
        $this->dirty = false;
    }

    /**
     * Stores information to database
     */
    public function save()
    {
        if ($this->dirty) {
            file_put_contents($this->filename, json_encode($this->storage, JSON_PRETTY_PRINT));
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
     */
    private function load()
    {
        if (file_exists($this->filename)) {
            if ((filemtime($this->filename) + $this->ttl) < time()) {
                unlink($this->filename);
            } else {
                $content = file_get_contents($this->filename);
                if ($content) {
                    $json = json_decode($content, true);
                    if ($json) {
                        return $json;
                    }
                }
            }
        }
        return [];
    }

    /**
     * @param string $directory
     */
    public static function flush($directory)
    {
        $files = scandir($directory);
        foreach ($files as $item) {
            if ($item !== '.' && $item !== '..' && is_file($directory . $item)) {
                unlink($directory . $item);
            }
        }
    }

    /**
     * @param string $directory
     * @return array
     */
    private function processIndex($directory)
    {
        $indexFile = $directory . 'index.json';
        if (! file_exists($indexFile)) {
            $this->flush($directory);
        } else {
            $content = file_get_contents($indexFile);
            if ($content) {
                $json = json_decode($content, true);
                $now = time();
                $dirty = false;
                foreach ($json as $name => $expiration) {
                    if ($expiration < $now) {
                        unset($json[$name]);
                        $dirty = true;
                    }
                }
                $files = scandir($directory);
                foreach ($files as $item) {
                    if ($item != 'index.json' && preg_match('/\.json$/', $item)) {
                        $name = str_replace('.json', '', $item);
                        if (! isset($json[$name])) {
                            unlink($directory . $item);
                        }
                    }
                }
                if ($dirty) {
                    file_put_contents($directory . 'index.json', json_encode($json, JSON_PRETTY_PRINT));
                }
                return $json;
            }
        }
        return [];
    }

}