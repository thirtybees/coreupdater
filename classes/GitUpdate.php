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
     * Storage items NOT changing when choosing a different original/target
     * version. Not deleting them speeds up comparison for merchants dialing
     * through several versions.
     */
    const STORAGE_PERMANENT_ITEMS = [
        // File lists for stable versions.
        '#^fileList-[0-9\.]+$#',
    ];
    /**
     * Directory where update files get downloaded to, with their full
     * directory hierarchy.
     */
    const DOWNLOADS_PATH = _PS_CACHE_DIR_.'/GitUpdateDownloads';
    /**
     * Path of the update script.
     */
    const SCRIPT_PATH = _PS_CACHE_DIR_.'/GitUpdateScript.php';

    /**
     * Set of regular expressions for removing file paths from the list of
     * files of a full release package. Matching paths get ignored by
     * comparions and by updates.
     */
    const RELEASE_FILTER = [
        '#^install/#',
        '#^modules/#',
        '#^mails/en/.*\.txt$#',
        '#^mails/en/.*\.tpl$#',
        '#^mails/en/.*\.html$#',
    ];
    /**
     * Set of regular expressions for removing file paths from the list of
     * local files. Files in either the original or the target release and not
     * filtered by RELEASE_FILTER get always onto the list.
     */
    const INSTALLATION_FILTER = [
        '#^cache/#',
        '#^config/#',
        '#^img/#',
        '#^upload/#',
        '#^download/#',
        '#^translations/#',
        '#^mails/#',
        '#^override/#',
        '#^themes/(?!community-theme-default/)#',
        '#^themes/community-theme-default/cache/#',
        '#^themes/community-theme-default/lang/#',
        '#^themes/community-theme-default/mails/#',
        '#^.htaccess$#',
        '#^robots.txt$#',
    ];
    /**
     * These files are left untouched even if they come with one of the
     * releases. All these files shouldn't be distributed in this location, to
     * begin with, but copied there from install/ at installation time.
     */
    const KEEP_FILTER = [
        '#^img/favicon.ico$#',
        '#^img/favicon_[0-9]+$#',
        '#^img/logo.jpg$#',
        '#^img/logo_stores.png$#',
        '#^img/logo_invoice.jpg$#',
        '#^img/c/[0-9-]+_thumb.jpg$#',
        '#^img/s/[0-9]+.jpg$#',
        '#^img/t/[0-9]+.jpg$#',
        '#^img/cms/cms-img.jpg$#',
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
     * @var GuzzleHttp
     */
    protected $guzzle = null;

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
     * Get Guzzle instance. Same basic parameters for all usages.
     *
     * @return GuzzleHttp Singleton instance of class GuzzleHttp\Client.
     *
     * @since 1.0.0
     */
    protected function getGuzzle()
    {
        if ( ! $this->guzzle) {
            $this->guzzle = new \GuzzleHttp\Client([
                'base_uri'    => AdminCoreUpdaterController::API_URL,
                'verify'      => _PS_TOOL_DIR_.'cacert.pem',
                'timeout'     => 20,
            ]);
        }

        return $this->guzzle;
    }

    /**
     * Do one step to build a comparison, starting with the first step.
     * Subsequent calls see results of the previous step and proceed with the
     * next one accordingly.
     *
     * @param array $messages Prepared array to append messages to. Format see
     *                        AdminCoreUpdaterController->ajaxProcess().
     * @param string $version Version to compare the installation on disk to.
     *
     * @since 1.0.0
     */
    public static function compareStep(&$messages, $version)
    {
        $me = static::getInstance();

        // Dump very old storage.
        if (file_exists(static::STORAGE_PATH)
            && time() - filemtime(static::STORAGE_PATH) > 86400) {
            static::deleteStorage(true);
        }

        // Reset an invalid storage set.
        if ( ! array_key_exists('versionOrigin', $me->storage)
            || $me->storage['versionOrigin'] !== _TB_VERSION_
            || ! array_key_exists('versionTarget', $me->storage)
            || $me->storage['versionTarget'] !== $version) {

            static::deleteStorage(false);

            $me->storage['versionOrigin'] = _TB_VERSION_;
            $me->storage['versionTarget'] = $version;
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
    }

    /**
     * Delete storage. Which means, the next compareStep() call starts over.
     *
     * @since 1.0.0
     */
    public static function deleteStorage($fullDelete = true)
    {
        $me = static::getInstance();

        if ($fullDelete) {
            $me->storage = [];
        } else {
            foreach (array_keys($me->storage) as $key) {
                $keep = false;
                foreach (static::STORAGE_PERMANENT_ITEMS as $filter) {
                    if (preg_match($filter, $key)) {
                        $keep = true;
                    }
                }
                if ( ! $keep) {
                    unset($me->storage[$key]);
                }
            }
        }
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
        $guzzle = $this->getGuzzle();
        $response = false;
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
                    foreach (static::KEEP_FILTER as $filter) {
                        if (preg_match($filter, $path)) {
                            $keep = false;
                            break;
                        }
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
        $targetList = $this->storage['fileList-'.$this->storage['versionTarget']];
        $originList = $this->storage['fileList-'.$this->storage['versionOrigin']];

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
                if ( ! array_key_exists($path, $targetList)
                    && ! array_key_exists($path, $originList)) {
                    foreach (static::INSTALLATION_FILTER as $filter) {
                        if (preg_match($filter, $path)) {
                            $keep = false;
                            break;
                        }
                    }
                }
                if ($keep) {
                    foreach (static::KEEP_FILTER as $filter) {
                        if (preg_match($filter, $path)) {
                            $keep = false;
                            break;
                        }
                    }
                }

                if ($keep) {
                    $this->storage['installationList'][$path]
                        = static::getGitHash($path);
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

    /**
     * Do one update step, starting with the first step. Subsequent calls see
     * results of the previous step and proceed with the next one accordingly.
     *
     * @param array $messages Prepared array to append messages to. Format see
     *                        AdminCoreUpdaterController->ajaxProcess().
     * @param string $version Unused, for signature compatibility with
     *                        compareStep().
     *
     * @since 1.0.0
     */
    public static function updateStep(&$messages, $version)
    {
        $me = static::getInstance();

        if ( ! array_key_exists('versionTarget', $me->storage)
            || ! array_key_exists('changeset', $me->storage)
            || ! array_key_exists('change', $me->storage['changeset'])
            || ! array_key_exists('add', $me->storage['changeset'])
            || ! array_key_exists('remove', $me->storage['changeset'])
            || ! array_key_exists('obsolete', $me->storage['changeset'])) {
            $messages['informations'][] = $me->l('Crucial storage set missing, please report this on Github.');
            $messages['error'] = true;
        } elseif ( ! array_key_exists('downloads', $me->storage)) {
            $me->storage['downloads']
                = array_merge($me->storage['changeset']['change'],
                              $me->storage['changeset']['add']);
            Tools::deleteDirectory(static::DOWNLOADS_PATH);
            mkdir(static::DOWNLOADS_PATH, 0777, true);

            $messages['informations'][] = sprintf($me->l('Downloads calculated, %d files to download.'), count($me->storage['downloads']));
            $messages['done'] = false;
        } elseif (count($me->storage['downloads'])) {
            $downloadSuccess = $me->downloadFiles();
            if ($downloadSuccess === true) {
                $messages['informations'][] = sprintf($me->l('Downloaded a couple of files, %d files remaining.'), count($me->storage['downloads']));
                $messages['done'] = false;
            } else {
                $messages['informations'][] = sprintf($me->l('Failed to download files with error: %s'), $downloadSuccess);
                $messages['error'] = true;
            }
        } elseif ( ! array_key_exists('updateScript', $me->storage)) {
            $scriptSuccess = $me->createUpdateScript();
            if ($scriptSuccess === true) {
                $messages['informations'][] = $me->l('Created update script.');
                $messages['done'] = false;
            } else {
                $messages['informations'][] = sprintf($me->l('Could not create update script, error: %s'), $scriptSuccess);
                $messages['error'] = true;
            }
        } else {
            $messages['informations'][] = '...completed.';
            $messages['done'] = true;
        }
    }

    /**
     * Download a couple of files from the Git repository on the thirty bees
     * server and save them in the cache directory.
     *
     * $this->storage['versionTarget'] and $this->storage['downloads'] are
     * expected to be valid.
     *
     * On return, successfully downloaded files are removed from
     * $this->storage['downloads'].
     *
     * @return bool|string Boolean true on success, error message on failure.
     *
     * @since 1.0.0
     */
    protected function downloadFiles()
    {
        $adminDir = false;
        if (defined('_PS_ADMIN_DIR_')) {
            $adminDir = str_replace(_PS_ROOT_DIR_, '', _PS_ADMIN_DIR_);
            $adminDir = trim($adminDir, '/').'/';
        }

        $pathList = array_slice(array_keys($this->storage['downloads']), 0, 100);
        foreach ($pathList as &$path) {
            if ($adminDir) {
                $path = preg_replace('#^'.$adminDir.'#', 'admin/', $path);
            }
        }

        $downloadCountBefore = count($this->storage['downloads']);
        $archiveFile = tempnam(_PS_CACHE_DIR_, 'GitUpdate');
        if ( ! $archiveFile) {
            return $this->l('Could not create temporary file for download.');
        }

        $guzzle = $this->getGuzzle();
        try {
            $guzzle->post('installationmaster.php', [
                'form_params' => [
                    'revision'  => $this->storage['versionTarget'],
                    'archive'   => $pathList,
                ],
                'sink'        => $archiveFile,
            ]);
        } catch (Exception $e) {
            unlink($archiveFile);

            return trim($e->getMessage());
        }
        $magicNumber = file_get_contents($archiveFile, false, null, 0, 2);
        if (filesize($archiveFile) < 100 || strcmp($magicNumber, "\x1f\x8b")) {
            // It's an error message response.
            $message = file_get_contents($archiveFile);
            unlink($archiveFile);

            return $message;
        }

        $archive = new Archive_Tar($archiveFile, 'gz');
        $archivePaths = $archive->listContent();
        if ($archive->error_object) {
            unlink($archiveFile);

            return 'Archive_Tar: '.$archive->error_object->message;
        }

        $archive->extract(static::DOWNLOADS_PATH);
        if ($archive->error_object) {
            unlink($archiveFile);

            return 'Archive_Tar: '.$archive->error_object->message;
        }

        // Verify whether each downloaded file matches the expected Git hash
        // and if so, remove if from the list of files to download. Delete
        // files on disk not matching (there should be none).
        $fileListName = 'fileList-'.$this->storage['versionTarget'];
        foreach ($archivePaths as $path) {
            if ($path['typeflag'] == 0) {
                $path = $path['filename'];

                $finalPath = $path;
                if ($adminDir) {
                    $finalPath = preg_replace('#^admin/#', $adminDir, $path);
                }

                if (static::getGitHash(static::DOWNLOADS_PATH.'/'.$path)
                    === $this->storage[$fileListName][$finalPath]) {
                    unset($this->storage['downloads'][$finalPath]);
                } else {
                    unlink(static::DOWNLOADS_PATH.'/'.$path);
                }
            }
        }

        // With all files downloaded, also rename admin/ on disk.
        $success = true;
        $from = static::DOWNLOADS_PATH.'/admin';
        if ( ! count($this->storage['downloads'])
            && $adminDir && is_dir($from)) {
            $to = static::DOWNLOADS_PATH.'/'.trim($adminDir, '/');

            $success = rename($from, $to);
            if ( ! $success) {
                $success = sprintf($this->l('Could not rename %s to %s.'), $from, $to);
            }
        }

        if ($success === true
            && $downloadCountBefore === count($this->storage['downloads'])) {
            $success = $this->l('Downloaded files successfully, but found no valid files in there.');
        }

        unlink($archiveFile);

        return $success;
    }

    /**
     * Create an update script which updates the shop installation
     * independently. During runtime of this script, the shop installation
     * has to be assumed to be broken, so it may call only bare PHP functions.
     * After creation, this script will be called from JavaScript directly.
     *
     * $this->storage['versionTarget'] and $this->storage['changeset'] are
     * expected to be valid.
     *
     * On return, $this->storage['updateScript'] is set to the path of the
     * script, relative to the shop root.
     *
     * @return bool|string Boolean true on success, error message on failure.
     *
     * @since 1.0.0
     */
    protected function createUpdateScript()
    {
        $success = true;

        $script = "<?php\n\n";
        $renameFormat = '@rename(\'%s\', \'%s\');'."\n";
        $removeFormat = '@unlink(\'%s\');'."\n";
        $createDirFormat = '@mkdir(\'%s\', 0777, true);'."\n";
        $removeDirFormat = '@rmdir(\'%s\');'."\n";

        $movePaths = array_merge($this->storage['changeset']['change'],
                                 $this->storage['changeset']['add']);
        foreach ($movePaths as $path => $manual) {
            $script .= sprintf($createDirFormat,
                               dirname(_PS_ROOT_DIR_.'/'.$path));
            $script .= sprintf($renameFormat,
                               static::DOWNLOADS_PATH.'/'.$path,
                               _PS_ROOT_DIR_.'/'.$path);
        }

        $removePaths = $this->storage['changeset']['remove'];
        foreach ($removePaths as $path => $manual) {
            $script .= sprintf($removeFormat, _PS_ROOT_DIR_.'/'.$path);

            // Remove containing folder. Fails silently if not empty.
            foreach ([1, 2, 3, 4, 5] as $dummy) {
                $path = dirname($path);
                if ($path === '.') {
                    break;
                }

                $script .= sprintf($removeDirFormat, _PS_ROOT_DIR_.'/'.$path);
            }
        }

        $script .= sprintf($removeFormat, _PS_CACHE_DIR_.'/class_index.php');
        $script .= 'if (function_exists(\'opcache_reset\')) {'."\n";
        $script .= '    opcache_reset();'."\n";
        $script .= '}'."\n";

        $success = (bool) file_put_contents(static::SCRIPT_PATH, $script);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(static::SCRIPT_PATH);
        }
        $this->storage['updateScript']
            = preg_replace('#^'._PS_ROOT_DIR_.'#', '', static::SCRIPT_PATH);

        return $success;
    }

    /**
     * Calculate Git hash of a file on disk.
     *
     * @param string $path Path of the file.
     *
     * @return string Hash.
     *
     * @since 1.0.0
     */
    public static function getGitHash($path)
    {
        $content = file_get_contents($path);

        return sha1('blob '.strlen($content)."\0".$content);
    }
}
