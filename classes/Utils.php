<?php
/**
 * Copyright (C) 2017-2024 thirty bees
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
 * @copyright 2017-2024 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

namespace CoreUpdater;

use PrestaShopException;

class Utils
{
    /**
     * Calculate Git hash of a file on disk.
     *
     * Works for files with a size of up to half of available memory, only.
     * Which should be plenty for distribution files. Biggest file distributed
     * with v1.0.8 was 1835770 bytes (1.75 MiB).
     *
     * @param string $path Path of the file.
     *
     * @return bool|string Hash, or boolean false on expected memory exhaustion.
     *
     * @version 1.0.0 Initial version.
     * @version 1.0.1 Predict memory exhaustion.
     */
    public static function getGitHash($path)
    {
        static $memoryLimit = false;
        if ( ! $memoryLimit) {
            $memoryLimit = \Tools::getMemoryLimit();
        }
        // Retrocompatibility with thirty bees <= 1.1.0.
        if ((int) $memoryLimit <= 0) {
            $memoryLimit = PHP_INT_MAX;
        }

        // Predict memory exhaution.
        // 2x file size + already used memory + 1 MiB was tested to be safe.
        if (filesize($path) * 2 + memory_get_usage() + 1048576 > $memoryLimit) {
            return false;
        }

        $content = file_get_contents($path);

        return sha1('blob '.strlen($content)."\0".$content);
    }

    /**
     * Normalize directory path by replacing windows slashes with linux-style slashes, and ensuring
     * that directory ends with slash
     *
     * @param string $dir directory
     * @return string normalized directory path
     */
    public static function normalizeDirectory($dir)
    {
        $dir = str_replace('\\', '/', $dir);
        return rtrim($dir, '/') . '/';
    }

    /**
     * Scan a directory filtered. Applying the filter immediately avoids
     * diving into directories which get filtered away anyways. This enhances
     * performance a lot, e.g. on large product image directories.
     *
     * @param string $dir       Full path of the directory to scan.
     * @param bool   $recursive Wether to scan the directory recursively.
     * @param array  $filter    List of regular expressions to filter against.
     *                          Paths matching one of these won't get listed.
     *
     * @return array List of paths of files found, without directories.
     *
     * @version 1.1.0 Initial version.
     */
    public static function scandir($dir, $recursive = false, $filter = [])
    {
        $pathList = [];
        foreach (scandir($dir) as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $path = static::normalizeDirectory($dir) . $file;
            // Strip leading './'.
            $path = preg_replace('#^\./#', '', $path);

            $keep = true;
            foreach ($filter as $regexp) {
                if (preg_match($regexp, $path)) {
                    $keep = false;
                    break;
                }
            }
            if ( ! $keep) {
                continue;
            }

            if (is_dir($path)) {
                if ($recursive) {
                    $pathList = array_merge(
                        $pathList,
                        static::scandir($path, $recursive, $filter)
                    );
                }
            } else {
                $pathList[] = $path;
            }
        }

        return $pathList;
    }

    /**
     * @param int $length
     * @return string
     */
    public static function generateRandomString($length = 32)
    {
        $ret = null;
        if (function_exists('random_bytes')) {
            try {
                $ret = md5(random_bytes(16));
            } catch (\Exception $ignored) {}
        }
        if (! $ret) {
            srand();
            $ret = md5(time() . rand());
        }
        return substr($ret, 0, $length);
    }

    /**
     * Returns true, if $path is safe relative file path: not absolute, without '..' segments
     * and NUL bytes. In strict mode only limited set of characters is allowed
     *
     * @param string $path
     * @param bool $strict
     * @return bool
     */
    public static function isSafeRelativePath($path, $strict = true)
    {
        if (! is_string($path) || $path === '' || strpos($path, "\0") !== false) {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\' || preg_match('#^[a-zA-Z]:#', $path)) {
            return false;
        }
        foreach (preg_split('#[/\\\\]#', $path) as $segment) {
            if ($segment === '..') {
                return false;
            }
        }
        if ($strict && ! preg_match('#^[A-Za-z0-9._/@+ -]+$#', $path)) {
            return false;
        }
        return true;
    }

    /**
     * Returns true, if $version is valid version or revision string
     *
     * @param string $version
     * @return bool
     */
    public static function isValidVersion($version)
    {
        return is_string($version) && (bool)preg_match('#^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$#', $version);
    }

    /**
     * Denies web access to directory by creating .htaccess and index.php files in it
     *
     * @param string $dir directory
     */
    public static function protectDirectory($dir)
    {
        $dir = static::normalizeDirectory($dir);
        if (! @is_dir($dir)) {
            return;
        }
        if (! @file_exists($dir . '.htaccess')) {
            @file_put_contents($dir . '.htaccess', implode("\n", [
                '# Apache 2.4',
                '<IfModule mod_authz_core.c>',
                '    Require all denied',
                '</IfModule>',
                '# Apache 2.2',
                '<IfModule !mod_authz_core.c>',
                '    Order deny,allow',
                '    Deny from all',
                '</IfModule>',
                ''
            ]));
        }
        if (! @file_exists($dir . 'index.php')) {
            @file_put_contents($dir . 'index.php', implode("\n", [
                '<?php',
                "header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');",
                "header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');",
                "header('Cache-Control: no-store, no-cache, must-revalidate');",
                "header('Cache-Control: post-check=0, pre-check=0', false);",
                "header('Pragma: no-cache');",
                "header('Location: ../');",
                'exit;',
                ''
            ]));
        }
    }

    /**
     * Returns cryptographically secure random secret, hex encoded
     *
     * @return string
     * @throws PrestaShopException
     */
    public static function generateSecret()
    {
        $bytes = null;
        if (function_exists('random_bytes')) {
            try {
                $bytes = random_bytes(32);
            } catch (\Exception $ignored) {}
        }
        if (! $bytes && function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes(32, $strong);
            if (! $strong) {
                $bytes = null;
            }
        }
        if (! $bytes) {
            throw new PrestaShopException("Failed to generate secure random secret");
        }
        return bin2hex($bytes);
    }
}
