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
     * Set of regular expressions for removing file paths from the list of
     * files of a full release package. Matching paths get ignored by
     * comparions and by updates.
     */
    const RELEASE_FILTER = [
        '#^install/#',
        '#^modules/#',
        '#^img/#',
    ];
    /**
     * Set of regular expressions for removing file paths from the list of
     * local files.
     */
    const INSTALLATION_FILTER = [
        '#^cache/#',
    ];

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
                $messages['informations'][] =
                    sprintf($me->l('File list for version %s downloaded.'), $version)
                    .' '.sprintf($me->l('Found %d paths to consider.'), count($me->storage['fileList-'.$version]));
                $messages['done'] = false;
            } else {
                $messages['informations'][] = sprintf($me->l('Failed to download file list for version %s with error: %s'), $version, $downloadSuccess);
                $messages['error'] = true;
            }
        } elseif ( ! array_key_exists('fileList-'._TB_VERSION_, $me->storage)) {
            $downloadSuccess = $me->downloadFileList(_TB_VERSION_);
            if ($downloadSuccess === true) {
                $messages['informations'][] =
                    sprintf($me->l('File list for version %s downloaded.'), _TB_VERSION_);
                $messages['done'] = false;
            } else {
                $messages['informations'][] = sprintf($me->l('Failed to download file list for version %s with error: %s'), _TB_VERSION_, $downloadSuccess);
                $messages['error'] = true;
            }
        } elseif ( ! array_key_exists('topLevel-'.$version, $me->storage)) {
            $me->extractTopLevelDirs($version);
            $me->storage['installationList'] = [];

            $messages['informations'][] = $me->l('Extracted top level directories.');
            $messages['done'] = false;
        } elseif (count($me->storage['topLevel-'.$version])) {
            $dir = array_pop($me->storage['topLevel-'.$version]);
            $me->searchInstallation($dir);

            $messages['informations'][] = sprintf($me->l('Searched installed files in %s/'), $dir);
            $messages['done'] = false;
        } else {
            $me->calculateChanges();
            $messages['changeset'] = $me->storage['changeset'];

            $messages['informations'][] = $me->l('Changeset calculated. Done.');
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

            $adminDir = false;
            if (defined('_PS_ADMIN_DIR_')) {
                $adminDir = str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_);
                $adminDir = trim($adminDir, '/').'/';
            }

            foreach (json_decode($response) as $line) {
                // An incoming line is like '<permissions> blob <sha1>\t<path>'.
                // Use explode limits, to allow spaces in the last field.
                $fields = explode(' ', $line, 3);
                $line = $fields[2];
                $fields = explode("\t", $line, 2);
                $path = $fields[1];
                $hash = $fields[0];

                $keep = true;
                foreach (static::RELEASE_FILTER as $filter) {
                    if (preg_match($filter, $path)) {
                        $keep = false;
                        break;
                    }
                }

                if ($keep) {
                    if ($adminDir) {
                        $path = preg_replace('#^admin/#', $adminDir, $path);
                    }

                    $fileList[$path] = $hash;
                }
            }
        }
        if ($fileList === false) {
            return $this->l('Downloaded file list did not contain any file.');
        }

        $this->storage['fileList-'.$version] = $fileList;

        return true;
    }

    /**
     * Extract top level directories from one of the file path lists. Purpose
     * is to allow splitting searches through the entire installation into
     * smaller chunks. Always present is the root directory, '.'.
     *
     * On return, $this->storage['topLevel-'.$version] is set to the list of
     * paths. No failure expected.
     *
     * @param array $version Version of the file path list.
     *
     * @since 1.0.0
     */
    protected function extractTopLevelDirs($version)
    {
        $fileList = $this->storage['fileList-'.$version];

        $topLevelDirs = ['.'];
        foreach ($fileList as $path => $hash) {
            $slashPos = strpos($path, '/');

            // Ignore files at root level.
            if ($slashPos === false) {
                continue;
            }

            $topLevelDir = substr($path, 0, $slashPos);

            // vendor/ is huge, so take a second level.
            if ($topLevelDir === 'vendor') {
                $slashPos = strpos($path, '/', $slashPos + 1);
                if ($slashPos) {
                    $topLevelDir = substr($path, 0, $slashPos);
                }
            }

            if ( ! in_array($topLevelDir, $topLevelDirs)) {
                $topLevelDirs[] = $topLevelDir;
            }
        }

        $this->storage['topLevel-'.$version] = $topLevelDirs;
    }

    /**
     * Search installed files in a directory recursively and add them to
     * $this->storage['installationList'] together with their Git hashes.
     *
     * Directories '.' and 'vendor' get searched not recursively. Note that
     * subdirectories of 'vendor' get searched as well, recursively.
     *
     * No failure expected, a not existing directory doesn't add anything.
     *
     * @param string $dir Directory to search.
     *
     * @since 1.0.0
     */
    protected function searchInstallation($dir)
    {
        $oldCwd = getcwd();
        chdir(_PS_ROOT_DIR_);

        if (is_dir($dir)) {
            if (in_array($dir, ['.', 'vendor'])) {
                $iterator = new DirectoryIterator($dir);
            } else {
                $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($dir)
                            );
            }

            foreach ($iterator as $fileInfo) {
                $path = $fileInfo->getPathname();
                if (in_array(basename($path), ['.', '..'])
                    || is_dir($path)) {
                    continue;
                }
                // Strip leading './'.
                $path = preg_replace('#(^./)#', '', $path);

                $keep = true;
                foreach (static::INSTALLATION_FILTER as $filter) {
                    if (preg_match($filter, $path)) {
                        $keep = false;
                        break;
                    }
                }

                if ($keep) {
                    $content = file_get_contents($path);
                    $hash = sha1('blob '.strlen($content)."\0".$content);

                    $this->storage['installationList'][$path] = $hash;
                }
            }
        }

        chdir($oldCwd);
    }

    /**
     * Calculate all the changes between the selected version and the current
     * installation.
     *
     * On return, $this->storage['changeset'] exists and contains an array of
     * the following format:
     *
     *               [
     *                   'change' => [
     *                       '<path>' => <manual>,
     *                       [...],
     *                   ],
     *                   'add' => [
     *                       '<path>' => <manual>,
     *                       [...],
     *                   ],
     *                   'remove' => [
     *                       '<path>' => <manual>,
     *                       [...],
     *                   ],
     *                   'obsolete' => [
     *                       '<path>' => <manual>,
     *                       [...],
     *                   ],
     *               ]
     *
     *               Where <path> is the path of the file and <manual> is a
     *               boolean indicating whether a change/add/remove overwrites
     *               manual edits.
     *
     * @since 1.0.0
     */
    protected function calculateChanges()
    {
        $targetList = $this->storage['fileList-'.$this->storage['versionTarget']];
        $installedList = $this->storage['installationList'];
        $originList = $this->storage['fileList-'.$this->storage['versionOrigin']];

        $changeList   = [];
        $addList      = [];
        $removeList   = [];
        $obsoleteList = [];

        foreach ($targetList as $path => $hash) {
            if (array_key_exists($path, $installedList)) {
                // Files to change are all files in the target version not
                // matching the installed file.
                if ($installedList[$path] !== $hash) {
                    $manual = false;
                    if (array_key_exists($path, $originList)
                        && $installedList[$path] !== $originList[$path]) {
                        $manual = true;
                    }
                    $changeList[$path] = $manual;
                } // else the file matches already.
            } else {
                // Files to add are all files in the target version not
                // existing locally.
                $addList[$path] = false;
            }
        }

        foreach ($installedList as $path => $hash) {
            if ( ! array_key_exists($path, $targetList)) {
                if (array_key_exists($path, $originList)) {
                    // Files to remove are all files not in the target version,
                    // but in the original version.
                    $manual = false;
                    if ($originList[$path] !== $hash) {
                        $manual = true;
                    }
                    $removeList[$path] = $manual;
                } else {
                    // Obsolete files are all files existing locally, but
                    // neither in the target nor in the original version.
                    $obsoleteList[$path] = true;
                }
            } // else handled above already.
        }

        $this->storage['changeset'] = [
            'change'    => $changeList,
            'add'       => $addList,
            'remove'    => $removeList,
            'obsolete'  => $obsoleteList,
        ];
    }
}
