<?php
/**
 * Copyright (C) 2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class GitUpdate.
 *
 * This class handles collected knowledge about a given update or version
 * comparison. It's designed to collect this knowledge in small steps, to give
 * merchants a good GUI feedback and avoid running into maximum execution time
 * limits.
 */
class GitUpdate
{
    /**
     * File to store collected knowledge about the update between invocations.
     * It should be a valid PHP file and gets written and included as needed.
     */
    const STORAGE_PATH = _PS_CACHE_DIR_.'/GitUpdateStorage.php';

    /**
     * @var GitUpdate
     */
    private static $instance = null;

    /**
     * Here all the collected data about an update gets stored. It gets saved
     * on disk between invocations.
     *
     * @var Array
     */
    protected $storage = [];

    /**
     * The signature prohibits instantiating a non-singleton class.
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        if (is_readable(static::STORAGE_PATH)) {
            require static::STORAGE_PATH;
        }
    }

    /**
     * @since 1.0.0
     */
    public function __destruct()
    {
        /**
         * Save storage. File format allows to include/require the resulting
         * file.
         */
        file_put_contents(static::STORAGE_PATH, '<?php
            $this->storage = '.var_export($this->storage, true).';
        ?>');
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(static::STORAGE_PATH);
        }
    }

    /**
     * Returns object instance. A singleton instance is maintained to allow
     * re-use of network connections and similar stuff.
     *
     * @return GitUpdate Singleton instance of class GitUpdate.
     *
     * @since 1.0.0
     */
    public static function getInstance()
    {
        if ( ! static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Do one step to build a comparison, starting with the first step.
     * Subsequent calls see results of the previous step and proceed with the
     * next one accordingly.
     *
     * @param array $messages Prepared array to append messages to. Format see
     *                        AdminCoreUpdaterController->ajaxProcessCompare().
     * @param string $version Version to compare the installation on disk to.
     *
     * @since 1.0.0
     */
    public static function compareStep(&$messages, $version)
    {
        $me = static::getInstance();

        // Demo processing. Replace with something meaningful.
        if ( ! array_key_exists('stepOne', $me->storage)) {
            sleep(5);
            $me->storage['stepOne'] = true;

            $messages['informations'][] = 'first step done.';
            $messages['done'] = false;
        } elseif ( ! array_key_exists('stepTwo', $me->storage)) {
            sleep(5);
            $me->storage['stepTwo'] = true;

            $messages['informations'][] = 'second step done.';
            $messages['done'] = false;
        } else {
            sleep(5);

            $messages['informations'][] = '...completed.';
            $messages['done'] = true;
        }

        // @TODO: remove this when using storage for actual updates.
        if ($messages['done']) {
            static::deleteStorage();
        }
    }

    /**
     * Delete storage. Which means, the next compareStep() call starts over.
     *
     * @since 1.0.0
     */
    public static function deleteStorage()
    {
        $me = static::getInstance();

        $me->storage = [];
    }
}
