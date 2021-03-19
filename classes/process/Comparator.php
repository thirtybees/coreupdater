<?php
/**
 * Copyright (C) 2019 - 2021 thirty bees
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
 * @copyright 2019 - 2021 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

namespace CoreUpdater;

use CoreUpdater\Api\ThirtybeesApi;
use CoreUpdater\Log\Logger;
use CoreUpdater\Process\ProcessingState;
use CoreUpdater\Process\Processor;
use CoreUpdater\Storage\Storage;
use CoreUpdater\Storage\StorageFactory;
use Exception;
use PrestaShopDatabaseException;
use PrestaShopException;


class Comparator extends Processor
{
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

    // This gets added with option 'Ignore the community theme' ON.
    const RELEASE_FILTER_THEME_OFF = [
        '#^themes/community-theme-default/#',
        '#^themes/niara/#',
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
        '#^.htaccess$#',
        '#^robots.txt$#',
    ];

    // This gets added with option 'Ignore the community theme' OFF.
    const INSTALLATION_FILTER_THEME_ON = [
        '#^themes/(?!community-theme-default/|niara/).+/#',
        '#^themes/community-theme-default/cache/#',
        '#^themes/community-theme-default/lang/#',
        '#^themes/community-theme-default/mails/#',
        '#^themes/niara/cache/#',
        '#^themes/niara/lang/#',
        '#^themes/niara/mails/#',
    ];

    // This gets added with option 'Ignore the community theme' ON.
    const INSTALLATION_FILTER_THEME_OFF = [
        '#^themes/#',
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
        '#^cache/smarty/.*index.php$#'
    ];

    const MANAGED_DIRECTORIES = [
        '#^classes/#' => true,
        '#^controllers/#' => true,
        '#^Core/#' => true,
        '#^Adapter/#' => true,
        '#^vendor/#' => false
    ];

    const ACTION_CHECK_INCOMPATIBLE_MODULES = 'CHECK_INCOMPATIBLE_MODULES';
    const ACTION_DOWNLOAD_FILES = 'DOWNLOAD_FILES';
    const ACTION_SEARCH_INSTALLATION = 'SEARCH_INSTALLATION';
    const ACTION_RESOLVE_TOP_DIRS = 'RESOLVE_TOP_DIRS';
    const ACTION_INCLUDE_DIST_FILESET = 'INCLUDE_DIST_FILESET';
    const ACTION_CALCULATE_CHANGES = 'CALCULATE_CHANGES';

    /**
     * @var ThirtybeesApi
     */
    private $api;

    /**
     * The signature prohibits instantiating a non-singleton class.
     *
     * @version 1.0.0 Initial version.
     *
     * @param Logger $logger
     * @param StorageFactory $storageFactory
     * @param ThirtybeesApi $api
     */
    public function __construct(
        Logger $logger,
        StorageFactory $storageFactory,
        ThirtybeesApi $api
    )  {
        parent::__construct($logger, $storageFactory);
        $this->api = $api;
    }

    protected function getProcessName()
    {
        return 'Compare';
    }

    /**
     * @param string $commit
     * @return bool
     */
    private static function isSha1($commit)
    {
        return (bool)preg_match('/^[0-9a-f]{40}$/i', $commit);
    }

    /**
     * @param string $revision
     * @return bool
     */
    private static function isVersion($revision)
    {
        return (bool)preg_match('/^[0-9.]+$/', $revision);
    }

    /**
     * @return string
     */
    public function getInstalledRevision()
    {
        if (defined('_TB_REVISION_'))  {
            if (static::isSha1(_TB_REVISION_)) {
                return _TB_REVISION_;
            }
            if (static::isVersion(_TB_REVISION_)) {
                return _TB_REVISION_;
            }
        }
        if (! static::isVersion(_TB_VERSION_)) {
            if (preg_match('/^([0-9.]+)-.*/', _TB_VERSION_, $matches)) {
                return $matches[1];
            }
        }

        return _TB_VERSION_;
    }

    /**
     * @param $settings
     * @return array
     * @throws Exception
     */
    protected function generateSteps($settings)
    {
        $installedRevision = $this->getInstalledRevision();
        $ignoreTheme = !! $this->getParameter('ignoreTheme', $settings);
        $targetRevision = $this->getParameter('targetRevision', $settings);
        $targetVersion = $this->getParameter('targetVersion', $settings);
        $versionName = $this->getParameter('versionName', $settings);
        $versionType = $this->getParameter('versionType', $settings);

        $steps = [];

        $steps[] = [
            'action' => static::ACTION_CHECK_INCOMPATIBLE_MODULES,
            'version' => $targetVersion
        ];

        $steps[] = [
            'action' => static::ACTION_DOWNLOAD_FILES,
            'revision' => $targetRevision,
            'ignoreTheme' => $ignoreTheme
        ];

        if ($installedRevision !== $targetRevision) {
            $steps[] = [
                'action' => static::ACTION_DOWNLOAD_FILES,
                'revision' => $installedRevision,
                'ignoreTheme' => $ignoreTheme
            ];
        }

        $steps[] = [
            'action' => static::ACTION_RESOLVE_TOP_DIRS,
            'revision' => $targetRevision
        ];

        $steps[] = [
            'action' => static::ACTION_SEARCH_INSTALLATION,
            'originRevision' => $installedRevision,
            'targetRevision' => $targetRevision,
            'ignoreTheme' => $ignoreTheme
        ];

        $steps[] = [
            'action' => static::ACTION_INCLUDE_DIST_FILESET,
            'originRevision' => $installedRevision,
            'targetRevision' => $targetRevision
        ];

        $steps[] = [
            'action' => static::ACTION_CALCULATE_CHANGES,
            'originRevision' => $installedRevision,
            'targetRevision' => $targetRevision,
            'targetVersion' => $targetVersion,
            'versionName' => $versionName,
            'versionType' => $versionType,
        ];

        return $steps;
    }

    /**
     * @param $processId
     * @param array $step
     * @param Storage $storage
     * @return ProcessingState
     * @throws Exception
     */
    protected function processStep($processId, $step, $storage)
    {
        $action = $this->getParameter('action', $step);
        switch ($action) {
            case static::ACTION_CHECK_INCOMPATIBLE_MODULES:
                return $this->checkModules($this->getParameter('version', $step));
            case static::ACTION_DOWNLOAD_FILES:
                return $this->downloadFileList(
                    $this->getParameter('revision', $step),
                    $this->getParameter('ignoreTheme', $step),
                    $storage
                );
            case static::ACTION_RESOLVE_TOP_DIRS:
                return $this->extractTopLevelDirs($this->getParameter('revision', $step), $storage);
            case static::ACTION_SEARCH_INSTALLATION:
                return $this->searchInstallationStep(
                    $this->getParameter('targetRevision', $step),
                    $this->getParameter('originRevision', $step),
                    $this->getParameter('ignoreTheme', $step),
                    $storage
                );
            case static::ACTION_INCLUDE_DIST_FILESET:
                return $this->addDistributionFileset(
                    $this->getParameter('targetRevision', $step),
                    $this->getParameter('originRevision', $step),
                    $storage
                );
            case static::ACTION_CALCULATE_CHANGES:
                return $this->calculateChanges(
                    $this->getParameter('targetVersion', $step),
                    $this->getParameter('targetRevision', $step),
                    $this->getParameter('originRevision', $step),
                    $this->getParameter('versionName', $step),
                    $this->getParameter('versionType', $step),
                    $storage
                );
            default:
                throw new Exception("Unknown action: $action");
        }
    }

    /**
     * @param array $step
     * @param Storage $storage
     * @return string
     * @throws Exception
     */
    protected function describeStep($step, $storage)
    {
        $action = $this->getParameter('action', $step);
        switch ($action) {
            case static::ACTION_CHECK_INCOMPATIBLE_MODULES:
                return $this->l("Checking modules compatibility");
            case static::ACTION_DOWNLOAD_FILES:
                return sprintf($this->l("Retrieving list of files for version %s"), $this->getParameter('revision', $step));
            case static::ACTION_RESOLVE_TOP_DIRS:
                return $this->l("Resolving top level directories");
            case static::ACTION_SEARCH_INSTALLATION:
                $all = $storage->get('topLevel');
                $index = $storage->hasKey('topLevelIndex') ? $storage->get('topLevelIndex') : 0;
                $directory = array_key_exists($index, $all) ? $all[$index] : '.';
                return sprintf($this->l("Indexing files in directory '%s'"), $directory);
            case static::ACTION_INCLUDE_DIST_FILESET:
                return $this->l("Indexing extra distribution files");
            case static::ACTION_CALCULATE_CHANGES:
                return $this->l("Calculating changes");
            default:
                throw new Exception("Unknown action: $action");
        }
    }

    /**
     * @param $targetVersion
     * @return ProcessingState
     */
    protected function checkModules($targetVersion)
    {
        $incompatibleModules = Retrocompatibility::getIncompatibleModules($targetVersion);
        if ($incompatibleModules) {
            return ProcessingState::failed(
                sprintf($this->l('Found %s installed modules incompatible with thirty bees %s.'), count($incompatibleModules), $targetVersion),
                implode("\n", $incompatibleModules)
            );
        } else {
            return ProcessingState::done();
        }
    }

    /**
     * @param string $targetRevision
     * @param string $originRevision
     * @param boolean $ignoreTheme
     * @param Storage $storage
     * @return ProcessingState
     */
    protected function searchInstallationStep($targetRevision, $originRevision, $ignoreTheme, Storage $storage)
    {
        $all = $storage->get('topLevel');
        $index = $storage->hasKey('topLevelIndex') ? $storage->get('topLevelIndex') : 0;
        $count = count($all);
        if ($index >= $count) {
            return ProcessingState::done();
        }
        try {
            $directory = $all[$index];
            $this->searchInstallation(
                $directory,
                $targetRevision,
                $originRevision,
                $ignoreTheme,
                $storage
            );
            $index++;
            $storage->put('topLevelIndex', $index);
            if ($index >= $count) {
                return ProcessingState::done();
            } else {
                return ProcessingState::inProgress(ProcessingState::calculateProgress($count, $index));
            }
        } catch (Exception $e) {
            return ProcessingState::failed($e->getMessage(), $e->__toString());
        }
    }

    /**
     * Download a list of files for a given version
     *
     * Returned array contains key-value pair for each entry, path
     * and Git (SHA1) hash: ['<path>' => '<hash>']
     *
     * @param string $revision
     * @param boolean $ignoreTheme
     * @param Storage $storage
     * @return ProcessingState
     */
    protected function downloadFileList($revision, $ignoreTheme, Storage $storage)
    {
        try {
            $response = $this->api->downloadFileList($revision);
            $fileList = [];
            foreach ($response as $path => $hash) {
                if ($this->shouldProcessFile($path, $ignoreTheme)) {
                    $fileList[$path] = $hash;
                }
            }
            $storage->put('fileList-' . $revision, $fileList);
            return ProcessingState::done();
        } catch (Exception $e) {
            return ProcessingState::failed($e->getMessage(), $e->__toString());
        }
    }

    /**
     * Method returns true if file should be considered for update
     *
     * @param string $path file path to check
     * @param boolean $ignoreTheme
     * @return bool
     */
    protected function shouldProcessFile($path, $ignoreTheme)
    {
        // first check against list of ignored paths
        foreach (static::RELEASE_FILTER as $filter) {
            if (preg_match($filter, $path)) {
                return false;
            }
        }

        // now check theme ignore list
        if ($ignoreTheme) {
            foreach (static::RELEASE_FILTER_THEME_OFF as $filter) {
                if (preg_match($filter, $path)) {
                    return false;
                }
            }
        }

        // now check keep filter
        foreach (static::KEEP_FILTER as $filter) {
            if (preg_match($filter, $path)) {
                return false;
            }
        }

        // file is not ignored
        return true;
    }

    /**
     * Extract top level directories from one of the file path lists. Purpose
     * is to allow splitting searches through the entire installation into
     * smaller chunks. Always present is the root directory, '.'.
     *
     * On return, $storage['topLevel'] is set to the list of
     * paths. No failure expected.
     *
     * @param string $revision Version of the file path list.
     * @param Storage $storage
     * @return ProcessingState
     */
    protected function extractTopLevelDirs($revision, Storage $storage)
    {
        $fileList = $storage->get('fileList-'.$revision);

        $topLevelDirs = ['.'];
        foreach ($fileList as $path => $hash) {
            $slashPos = strpos($path, '/');

            // Ignore files at root level.
            if ($slashPos === false) {
                continue;
            }

            $topLevelDir = substr($path, 0, $slashPos);
            if ($topLevelDir !== 'vendor') {
                if (!in_array($topLevelDir, $topLevelDirs)) {
                    $topLevelDirs[] = $topLevelDir;
                }
            }
        }

        // process vendor directory
        $vendorDirs = scandir(_PS_ROOT_DIR_ . '/vendor');
        foreach ($vendorDirs as $item) {
            if ($item !== '.' && $item !== '..' && is_dir(_PS_ROOT_DIR_ . '/vendor/' . $item)) {
                $topLevelDirs[] = 'vendor/' . $item;
            }
        }

        $storage->put('topLevel', $topLevelDirs);
        return ProcessingState::done();
    }

    /**
     * Search installed files in a directory recursively and add them to
     * $storage['installationList'] together with their Git hashes.
     *
     * Directories '.' and 'vendor' get searched not recursively. Note that
     * subdirectories of 'vendor' get searched as well, recursively.
     *
     * No failure expected, a not existing directory doesn't add anything.
     *
     * @param string $dir Directory to search.
     * @param string $targetRevision
     * @param string $originRevision
     * @param boolean $ignoreTheme
     * @param Storage $storage
     */
    protected function searchInstallation($dir, $targetRevision, $originRevision, $ignoreTheme, Storage $storage)
    {
        $this->logger->log('Indexing files in directory \'' . $dir . '\'');
        $targetList = $storage->get('fileList-'.$targetRevision);
        $originList = $storage->get('fileList-'.$originRevision);
        $installationList = $storage->hasKey('installationList') ? $storage->get('installationList') : [];
        $cnt = 0;

        $oldCwd = getcwd();
        try {
            chdir(_PS_ROOT_DIR_);

            if ($ignoreTheme) {
                $scanFilter = array_merge(static::INSTALLATION_FILTER,
                    static::INSTALLATION_FILTER_THEME_OFF);
            } else {
                $scanFilter = array_merge(static::INSTALLATION_FILTER,
                    static::INSTALLATION_FILTER_THEME_ON);
            }


            if (is_dir($dir)) {
                $recursive = true;
                if (in_array($dir, ['.', 'vendor'])) {
                    $recursive = false;
                }

                // Scan files on disk.
                foreach (Utils::scandir($dir, $recursive, $scanFilter) as $path) {
                    $cnt++;
                    if (array_key_exists($path, $targetList) || array_key_exists($path, $originList)) {
                        $installationList[$path] = Utils::getGitHash($path);
                    } else {
                        // Pointless to calculate a hash.
                        $installationList[$path] = true;
                    }
                }
            }

            if ($installationList) {
                $storage->put('installationList', $installationList);
            }
        } finally {
            chdir($oldCwd);
        }

        $this->logger->log('Found ' . $cnt . ' files');
    }

    /**
     * Add distribution files to the fileset of installed files. Only files
     * actually existing, of course.
     *
     * This is for those files which get filtered away by broad filters,
     * still should get considered. E.g. img/p/index.php, while files in
     * img/p/ get filtered.
     *
     * No failure expected, operations happen on existing data, only.
     *
     * @param string $targetRevision
     * @param string $originRevision
     * @param Storage $storage
     * @return ProcessingState
     */
    protected function addDistributionFileset($targetRevision, $originRevision, $storage)
    {
        $oldCwd = getcwd();
        chdir(_PS_ROOT_DIR_);
        try {

            $installationList = $storage->get('installationList');

            $fileList = $storage->get('fileList-' . $targetRevision);
            if ($originRevision != $targetRevision) {
                $fileList = array_merge($fileList, $storage->get('fileList-' . $originRevision));
            }

            $modified = false;
            foreach ($fileList as $path => $_) {
                if (@is_file($path) && !array_key_exists($path, $installationList)) {
                    $installationList[$path] = Utils::getGitHash($path);
                    $modified = true;
                }
            }

            if ($modified) {
                $storage->put('installationList', $installationList);
            }

            return ProcessingState::done();
        } finally {
            chdir($oldCwd);
        }
    }

    /**
     * Calculate all the changes between the selected version and the current
     * installation.
     *
     * On return, $storage['result']['changeSet'] exists and contains an array of
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
     *                   ]
     *               ]
     *
     *               Where <path> is the path of the file and <manual> is a
     *               boolean indicating whether a change/add/remove overwrites
     *               manual edits.
     *
     * @param string $targetVersion
     * @param string $targetRevision
     * @param string $originRevision
     * @param string $versionName
     * @param string $versionType
     * @param Storage $storage
     * @return ProcessingState
     */
    protected function calculateChanges(
        $targetVersion,
        $targetRevision,
        $originRevision,
        $versionName,
        $versionType,
        $storage
    ) {
        $installedList = $storage->get('installationList');
        $targetList = $storage->get('fileList-'.$targetRevision);
        $originList = $storage->get('fileList-'.$originRevision);

        $changeList   = [];
        $addList      = [];
        $removeList   = [];

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
                    foreach (static::MANAGED_DIRECTORIES as $pattern => $backup) {
                        if (preg_match($pattern, $path)) {
                            $removeList[$path] = $backup;
                        }
                    }
                }
            }
        }

        $storage->put('result', [
            'targetVersion' => $targetVersion,
            'targetRevision' => $targetRevision,
            'versionName' => $versionName,
            'versionType' => $versionType,
            'changeSet' => [
                'change'    => $changeList,
                'add'       => $addList,
                'remove'    => $removeList
            ]
        ]);

        return ProcessingState::done();
    }



    /**
     * @param string $processId
     * @param string $revision
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getFileList($processId, $revision)
    {
        $storage = $this->getStorage($processId);
        return $storage->get('fileList-' . $revision);
    }

    /**
     * @param string $processId
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getResult($processId)
    {
        $storage = $this->getStorage($processId);
        if ($storage->hasKey('result')) {
            return $storage->get('result');
        }
        return [];
    }
}
