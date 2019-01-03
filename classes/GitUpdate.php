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

        // Reset an invalid storage set.
        if ( ! array_key_exists('versionOrigin', $me->storage)
            || $me->storage['versionOrigin'] !== _TB_VERSION_
            || ! array_key_exists('versionTarget', $me->storage)
            || $me->storage['versionTarget'] !== $version) {
            $me->storage = [
                'versionOrigin' => _TB_VERSION_,
                'versionTarget' => $version,
            ];
        }

        // Do one compare step.
        if ( ! array_key_exists('fileList-'.$version, $me->storage)) {
            $downloadSuccess = $me->downloadFileList($version);
            if ($downloadSuccess === true) {
                $messages['informations'][] = sprintf($me->l('File list for version %s downloaded.'), $version);
                $messages['done'] = false;
            } else {
                $messages['informations'][] = sprintf($me->l('Failed to download file list for version %s with error: %s'), $version, $downloadSuccess);
                $messages['error'] = true;
            }
        } else {
            $messages['informations'][] = $me->l('...completed.');
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

    /**
     * Get translation for a given text.
     *
     * @param string $string String to translate.
     *
     * @return string Translation.
     *
     * @since 1.0.0
     */
    protected function l($string)
    {
        return Translate::getModuleTranslation('coreupdater', $string,
                                               'coreupdater');
    }

    /**
     * Download a list of files for a given version from api.thirtybees.com and
     * store it in $this->storage['fileList-'.$version] as a proper PHP array.
     *
     * For efficiency (a response can easily contain 10,000 lines), the
     * returned array contains just one key-value pair for each entry, path
     * and Git (SHA1) hash: ['<path>' => '<hash>']. Permissions get ignored,
     * because all files should have 644 permissions.
     *
     * @param string $version List for this version.
     *
     * @return bool|string True on success, or error message on failure.
     *
     * @since 1.0.0
     */
    protected function downloadFileList($version)
    {
        $response = false;
        $guzzle = new \GuzzleHttp\Client([
            'base_uri'    => AdminCoreUpdaterController::API_URL,
            'verify'      => _PS_TOOL_DIR_.'cacert.pem',
            'timeout'     => 20,
        ]);
        try {
            $response = $guzzle->post('installationmaster.php', [
                'form_params' => [
                    'listrev' => $version,
                ],
            ])->getBody();
        } catch (Exception $e) {
            return trim($e->getMessage());
        }

        $fileList = false;
        if ($response) {
            $fileList = [];
            foreach (json_decode($response) as $line) {
                // An incoming line is like '<permissions> blob <sha1>\t<path>'.
                // Use explode limits, to allow spaces in the last field.
                $fields = explode(' ', $line, 3);
                $line = $fields[2];
                $fields = explode("\t", $line, 2);

                $fileList[$fields[1]] = $fields[0];
            }
        }
        if ($fileList === false) {
            return $this->l('Downloaded file list did not contain any file.');
        }

        $this->storage['fileList-'.$version] = $fileList;

        return true;
    }
}
