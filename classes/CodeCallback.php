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

use Db;
use PrestaShopException;
use \ReflectionClass;
use \ReflectionException;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \AppendIterator;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class CodeCallback
 *
 * @version 1.1.0 Initial version.
 */
class CodeCallback
{
    const INITIALIZATION_CALLBACK_INTERFACE = '\Thirtybees\Core\InitializationCallback';
    const INITIALIZATION_CALLBACK_METHOD = 'initializationCallback';

    /**
     * Calls all callbacks defined in the core classes
     *
     * @param Db $db
     * @throws ReflectionException
     * @throws PrestaShopException
     */
    public function execute(Db $db)
    {
        if (! interface_exists(static::INITIALIZATION_CALLBACK_INTERFACE)) {
            return;
        }
        $dirs = ['classes', 'controllers', 'Core', 'Adapter'];
        $iterator = new AppendIterator();
        foreach ($dirs as $dir) {
            $iterator->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(_PS_ROOT_DIR_ . '/' . $dir)));
        }
        $list = [];
        foreach ($iterator as $path) {
            $list[] = "$path";
        }
        sort($list);
        foreach ($list as $path) {
            $file = basename($path);
            if (preg_match("/^.+\.php$/i", $file)) {
                $className = str_replace(".php", "", $file);
                if ($className !== "index") {
                    $namespace = $this->resolveNamespace($path);
                    if ($namespace) {
                        $className = $namespace . $className;
                    }
                    if (! class_exists($className)) {
                        require_once($path);
                    }
                    if (class_exists($className)) {
                        $reflection = new ReflectionClass($className);
                        if ($reflection->implementsInterface(static::INITIALIZATION_CALLBACK_INTERFACE) && !$reflection->isAbstract()) {
                            $callable = [$className, self::INITIALIZATION_CALLBACK_METHOD];
                            try {
                                $callable($db);
                            } catch (\Exception $e) {
                                throw new PrestaShopException("Error occurred during initialization of class " . $className . ": " . $e->getMessage(), 0, $e);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Extracts namespace from the php file, if exists
     *
     * @param string $path file path
     * @return string namespace or empty string
     */
    protected function resolveNamespace($path)
    {
        $content = @file_get_contents($path);
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (preg_match('#^\s*namespace\s+([^\s;]+)\s*;\s*$#', $line, $matches)) {
                $fileNamespace = trim($matches[1], '\\') . '\\';
                return $fileNamespace;
            }
        }
        return "";
    }

}
