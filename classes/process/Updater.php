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

use Adapter_Exception;
use CoreUpdater\Api\ThirtybeesApi;
use CoreUpdater\Log\Logger;
use CoreUpdater\Process\ProcessingState;
use CoreUpdater\Process\Processor;
use CoreUpdater\Storage\Storage;
use CoreUpdater\Storage\StorageFactory;
use Db;
use DbPDO;
use Exception;
use Archive_Tar;
use HTMLPurifier_Exception;
use PageCache;
use PrestaShopAutoload;
use PrestaShopDatabaseException;
use PrestaShopException;
use ReflectionException;
use Tools;
use Media;


class Updater extends Processor
{

    const ACTION_DOWNLOAD = 'DOWNLOAD_FILES';
    const ACTION_EXTRACT = 'EXTRACT_FILES';
    const ACTION_VERIFY = 'VERIFY_FILES';
    const ACTION_RENAME_DIR = 'RENAME_DIRECTORY';
    const ACTION_BACKUP = 'BACKUP';
    const ACTION_CREATE_UPDATE_SCRIPT = 'CREATE_UPDATE_SCRIPT';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_POST_PROCESSING = 'POST_PROCESSING';
    const ACTION_MIGRATE_DB = 'MIGRATE_DB';
    const ACTION_INITIALIZE_CODEBASE = 'INITIALIZE_CODEBASE';
    const ACTION_CLEANUP = 'CLEANUP';
    const ACTION_PREPARE_RESULT = 'PREPARE_RESULT';

    /**
     * @var ThirtybeesApi
     */
    private $api;

    /**
     * @var string Admin directory
     */
    private $adminDir;

    /**
     * @var string Root directory
     */
    private $rootDir;

    /**
     * @var string Backup directory
     */
    private $backupDir;

    /**
     * @var string Staging directory
     */
    private $stagingDir;

    /**
     * @var string Base url of the shop
     */
    private $baseUrl;

    /**
     * @var
     */
    private $chunkSize = 100;

    /**
     * The signature prohibits instantiating a non-singleton class.
     *
     * @version 1.0.0 Initial version.
     * @param Logger $logger
     * @param StorageFactory $storageFactory
     * @param ThirtybeesApi $api
     * @param string $adminDir
     * @param string $rootDir
     * @param $baseUrl
     */
    public function __construct(
        Logger $logger,
        StorageFactory $storageFactory,
        ThirtybeesApi $api,
        $adminDir,
        $rootDir,
        $baseUrl
    ) {
        parent::__construct($logger, $storageFactory);
        $this->api = $api;
        $this->adminDir = $adminDir;
        $this->rootDir = Utils::normalizeDirectory($rootDir);
        $this->stagingDir = $this->rootDir . 'cache/coreupdater/';
        $this->backupDir = $this->rootDir . $adminDir . '/backups/coreupdater/' .date('YmdHis');
        $this->baseUrl = $baseUrl;
    }

    protected function getProcessName()
    {
        return 'Update';
    }

    /**
     * @param $settings
     * @return array
     * @throws Exception
     */
    protected function generateSteps($settings)
    {
        $targetRevision = $settings['targetRevision'];
        $targetVersion = $settings['targetVersion'];
        $versionType = $settings['versionType'];
        $versionName = $settings['versionName'];
        $changeSet = $settings['changeSet'];
        $targetFileList = $settings['targetFileList'];
        $stagingDirectory = $this->stagingDir;
        $scriptPath = $this->rootDir . 'coreupdater.php';
        $scriptUrl = $this->baseUrl . '/coreupdater.php?' . time();

        Tools::deleteDirectory($stagingDirectory);
        mkdir($stagingDirectory, 0777, true);
        Tools::deleteDirectory($this->backupDir);

        $toDownload = $this->getFilesToDownload($changeSet, $targetFileList);
        $sources = [];

        $chunks = array_chunk(array_keys($toDownload), $this->chunkSize);
        $steps = [];
        $total = count($chunks);
        foreach ($chunks as $index => $files) {
            $chunk = $index+1;
            $chunkName = 'chunk-'. str_pad($chunk, 4, '0', STR_PAD_LEFT);
            $archive = $stagingDirectory . '/' . $chunkName . 'tar.gz';
            $dir = Utils::normalizeDirectory($stagingDirectory . $chunkName . '/');
            $filesWithHash = [];
            $admin = false;
            foreach ($files as $file) {
                $filesWithHash[$file] = $toDownload[$file];
                $targetFile = $this->fixAdminDirectory($file);
                if (strpos($file, 'admin/') === 0) {
                    $admin = true;
                }
                $sources[$dir . $targetFile] = $this->rootDir.$targetFile;
            }

            $steps[] = [
                'action' => static::ACTION_DOWNLOAD,
                'revision' => $targetRevision,
                'files' => $files,
                'chunk' => $chunk,
                'total' => $total,
                'target' => $archive
            ];
            $steps[] = [
                'action' => static::ACTION_EXTRACT,
                'source' => $archive,
                'target' => $dir,
                'chunk' => $chunk,
                'total' => $total
            ];
            $steps[] = [
                'action' => static::ACTION_VERIFY,
                'source' => $dir,
                'files' => $filesWithHash,
                'chunk' => $chunk,
                'total' => $total
            ];
            if ($admin && $this->adminDir !== 'admin') {
                $steps[] = [
                    'action' => static::ACTION_RENAME_DIR,
                    'from' => $dir . 'admin',
                    'to' => $dir . $this->adminDir
                ];
            }
        }

        $toBackup = $this->getFilesToBackup($changeSet);
        if ($toBackup) {
            $backupChunks = array_chunk($toBackup, $this->chunkSize);
            foreach ($backupChunks as $files) {
                $steps[] = [
                    'action' => static::ACTION_BACKUP,
                    'files' => $files,
                    'to' => $this->backupDir
                ];
            }
        }

        $toRemove = $this->getFilesToDelete($changeSet);
        $steps[] = [
            'action' => static::ACTION_CREATE_UPDATE_SCRIPT,
            'move' => $sources,
            'remove' => $toRemove,
            'scriptFile' => $scriptPath,
            'scriptUrl' => $scriptUrl
        ];

        $steps[] = [
            'action' => static::ACTION_UPDATE,
        ];

        $steps[] = [
            'action' => static::ACTION_POST_PROCESSING,
            'targetVersion' => $targetVersion,
            'targetRevision' => $targetRevision
        ];

        $steps[] = [
            'action' => static::ACTION_MIGRATE_DB,
        ];

        $steps[] = [
            'action' => static::ACTION_INITIALIZE_CODEBASE
        ];

        $steps[] = [
            'action' => static::ACTION_CLEANUP,
            'dirs' => [ $stagingDirectory ]
        ];

        $steps[] = [
            'action' => static::ACTION_PREPARE_RESULT,
            'versionType' => $versionType,
            'versionName' => $versionName
        ];

        return $steps;
    }

    /**
     * @param string $processId
     * @param array $step
     * @param Storage $storage
     * @return ProcessingState
     * @throws Exception
     */
    protected function processStep($processId, $step, $storage)
    {
        $action = $this->getParameter('action', $step);
        switch ($action) {
            case static::ACTION_DOWNLOAD:
                return $this->downloadChunk(
                    $step['revision'],
                    $step['files'],
                    $step['target']
                );
            case static::ACTION_EXTRACT:
                return $this->extractChunk(
                    $step['source'],
                    $step['target']
                );
            case static::ACTION_VERIFY:
                return $this->verifyChunk(
                    $step['source'],
                    $step['files']
                );
            case static::ACTION_RENAME_DIR:
                return $this->renameDir(
                    $step['from'],
                    $step['to']
                );
            case static::ACTION_BACKUP:
                return $this->backupFiles(
                    $step['files'],
                    $step['to']
                );
            case static::ACTION_CREATE_UPDATE_SCRIPT:
                return $this->createUpdateScript(
                    $processId,
                    $step['move'],
                    $step['remove'],
                    $step['scriptFile'],
                    $step['scriptUrl']
                );
            case static::ACTION_UPDATE:
                // nothing to do here, update was performed via ajax call to
                // generated update script
                return ProcessingState::done();
            case static::ACTION_POST_PROCESSING:
                return $this->afterUpdate(
                    $this->getParameter('targetVersion', $step),
                    $this->getParameter('targetRevision', $step)
                );
            case static::ACTION_MIGRATE_DB:
                return $this->migrateDb();
            case static::ACTION_INITIALIZE_CODEBASE:
                return $this->initializeCodebase();
            case static::ACTION_CLEANUP:
                return $this->cleanup($this->getParameter('dirs', $step));
            case static::ACTION_PREPARE_RESULT:
                return $this->prepareResult(
                    $this->getParameter('versionType', $step),
                    $this->getParameter('versionName', $step),
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
            case static::ACTION_DOWNLOAD:
                return sprintf($this->l("Downloading files, chunk %s out of %s"), $step['chunk'], $step['total']);
            case static::ACTION_EXTRACT:
                return sprintf($this->l("Extracting files, chunk %s out of %s"), $step['chunk'], $step['total']);
            case static::ACTION_VERIFY:
                return sprintf($this->l("Verifying downloaded files, chunk %s out of %s"), $step['chunk'], $step['total']);
            case static::ACTION_RENAME_DIR:
                return sprintf($this->l("Renaming directory %s to %s"), $step['from'], $step['to']);
            case static::ACTION_BACKUP:
                return $this->l("Backuping files");
            case static::ACTION_CREATE_UPDATE_SCRIPT:
                return $this->l("Generating update script");
            case static::ACTION_UPDATE:
                return $this->l("Executing update script");
            case static::ACTION_POST_PROCESSING:
                return $this->l("Update post processing");
            case static::ACTION_MIGRATE_DB:
                return $this->l("Migrating database");
            case static::ACTION_INITIALIZE_CODEBASE:
                return $this->l("Initializing codebase");
            case static::ACTION_CLEANUP:
                return $this->l("Cleaning up");
            case static::ACTION_PREPARE_RESULT:
                return $this->l('Finalizing update process...');
            default:
                throw new Exception("Unknown action: $action");
        }
    }


    /**
     * Download chunk of files as zip file
     *
     * @param string $revision
     * @param string[] $files
     * @param string $target
     * @return ProcessingState
     */
    protected function downloadChunk($revision, $files, $target)
    {
        $this->api->downloadFiles($revision, $files, $target);
        return ProcessingState::done();
    }

    /**
     * Extract downloaded archive
     *
     * @param string $source
     * @param string $target
     * @return ProcessingState
     * @throws Exception
     */
    protected function extractChunk($source, $target)
    {
        try {
            $archive = new Archive_Tar($source, 'gz');
            if ($archive->error_object) {
                throw new Exception("Downloaded archive $source is invalid" . $archive->error_object->message);
            }
            $archive->extract($target);
            if ($archive->error_object) {
                throw new Exception("Downloaded archive $source is invalid" . $archive->error_object->message);
            }
        } finally {
            @unlink($source);
        }
        return ProcessingState::done();
    }

    /**
     * @param $dir
     * @param $fileList
     * @return ProcessingState
     * @throws Exception
     */
    protected function verifyChunk($dir, $fileList)
    {
        // verify downloaded files
        foreach ($fileList as $path => $hash) {
            if (! @file_exists($dir . $path)) {
                return ProcessingState::failed(
                    sprintf($this->l("File %s not downloaded"), $path),
                    sprintf($this->l("File not found in %s"), $dir)
                );
            }
            $calculatedHash = Utils::getGitHash($dir . $path);

            if ($calculatedHash != $hash) {
                return ProcessingState::failed(
                    sprintf($this->l("File %s has invalid fingerprint"), $path),
                    (
                        "Calculated hash = $calculatedHash\n" .
                        "Expected hash = $hash"
                    )
                );
            }
        }

        // verify that none other files are present
        $existingFiles = Utils::scandir($dir, true);
        foreach ($existingFiles as $path) {
            $path = str_replace($dir, "", $path);
            if (! isset($fileList[$path])) {
                return ProcessingState::failed("There was extra file $path in archive", "Downloaded archive contained extra file that was not requested: $path");
            }
        }

        return ProcessingState::done();
    }

    /**
     * Renames directory
     *
     * @param string $from
     * @param string $to
     * @return ProcessingState
     * @throws Exception
     */
    protected function renameDir($from, $to)
    {
        if (! @is_dir($from)) {
            throw new Exception("Not a directory: $from");
        }
        if (! @rename($from, $to)) {
            throw new Exception("Failed to rename directory '$from' to '$to");
        }
        return ProcessingState::done();
    }

    /**
     * @param string[] $files
     * @param string $backupDir
     * @return ProcessingState
     */
    protected function backupFiles($files, $backupDir)
    {
        if (! file_exists($backupDir)) {
            mkdir($backupDir, 0777, true);
        }
        foreach ($files as $file) {
            $source = $this->rootDir . $file;
            if (file_exists($source)) {
                $target = $backupDir . '/' . $file;
                $dir = dirname($target);
                if (!@file_exists($dir)) {
                    @mkdir($dir, 0777, true);
                }
                @copy($source, $target);
            }
        }
        return ProcessingState::done();
    }

    /**
     * Creates update script
     *
     * @param string $processId
     * @param string[] $move
     * @param string[] $remove
     * @param string $scriptFile
     * @param string $scriptUrl
     * @return ProcessingState
     */
    protected function createUpdateScript($processId, $move, $remove, $scriptFile, $scriptUrl)
    {
        $renameFormat = '@rename(\'%s\', \'%s\');'."\n";
        $removeFormat = '@unlink(\'%s\');'."\n";
        $createDirFormat = '@mkdir(\'%s\', 0777, true);'."\n";
        $removeDirFormat = '@rmdir(\'%s\');'."\n";

        $script = "<?php\n\n";

        // check parameter processId
        $script .= "if (\$_POST['processId'] !== '$processId') {\n";
        $script .= '  die(\'{"success": false, "error": {"message": "Invalid process ID", "details": "Invalid process ID"}}\');'."\n";
        $script .= "}\n\n";

        // create directories
        $createDirs = [];
        foreach ($move as $target) {
            $dir = dirname($target);
            $createDirs[$dir] = $dir;
        }
        if ($createDirs) {
            sort($createDirs);
            $script .= "// Create directories\n";
            foreach ($createDirs as $dir) {
                $script .= sprintf($createDirFormat, $dir);
            }
            $script .= "\n\n";
        }

        if ($move) {
            $script .= "// Move downloaded files from staging directory\n";
            // move downloaded files
            foreach ($move as $source => $target) {
                $script .= sprintf($renameFormat, $source, $target);
            }
            $script .= "\n\n";
        }

        $removeDirs = [];
        if ($remove) {
            // delete obsolete files
            $script .= "// Remove obsolete files\n";
            foreach ($remove as $path) {
                $script .= sprintf($removeFormat, $this->rootDir . $path);
                for ($i = 0; $i < 10; $i++) {
                    $path = dirname($path);
                    if ($path === '.') {
                        break;
                    }
                    $removeDirs[$path] = $this->rootDir . $path;
                }
            }
            $script .= "\n\n";
        }

        // delete empty directories
        if ($removeDirs) {
            rsort($removeDirs);
            $script .= "// Remove directories, if not empty\n";
            foreach ($removeDirs as $removeDir) {
                $script .= sprintf($removeDirFormat, $removeDir);
            }
            $script .= "\n\n";
        }

        // delete index file
        $script .= "// Delete autoloader file\n";
        $script .= sprintf($removeFormat, $this->rootDir . PrestaShopAutoload::INDEX_FILE);

        // reset cache
        $script .= "\n\n// Reset opcache\n";
        $script .= 'if (function_exists(\'opcache_reset\')) {'."\n";
        $script .= '    opcache_reset();'."\n";
        $script .= '}'."\n";

        // Let the script file remove its self to indicate the execution and
        // avoid multiple execution.
        $script .= "\n\n// Cleanup - remove update file\n";
        $script .= sprintf($removeFormat, $scriptFile);

        // Die with a minimum response.
        $script .= "\n\n// Generate response\n";
        $script .= 'die(\'{"success": true}\');'."\n";

        $success = (bool) file_put_contents($scriptFile, $script);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($scriptFile);
        }

        return $success
            ? ProcessingState::done($scriptUrl)
            : ProcessingState::failed($this->l("Failed to create update file"), "Update file: $scriptFile\n\n\n$script");
    }

    /**
     * @param $targetVersion
     * @param $targetRevision
     * @return ProcessingState
     * @throws PrestaShopException
     */
    public function afterUpdate($targetVersion, $targetRevision)
    {
        // update _TB_VERSION_ and _TB_REVISION_ shop settings
        $settingsPath = _PS_CONFIG_DIR_.'settings.inc.php';
        $settings = @file_get_contents($settingsPath);
        $settings = preg_replace(
            '/define\s*\(\s*\'_TB_VERSION_\'\s*,\s*\'[\w.-]+\'\s*\)/',
            'define(\'_TB_VERSION_\', \''.$targetVersion.'\')',
            $settings
        );
        if (preg_match('/define\s*\(\s*\'_TB_REVISION_\'/', $settings)) {
            $settings = preg_replace(
                '/define\s*\(\s*\'_TB_REVISION_\'\s*,\s*\'[\w.-]+\'\s*\)/',
                'define(\'_TB_REVISION_\', \'' . $targetRevision . '\')',
                $settings
            );
        } else {
            $settings = rtrim($settings, "\n") . "\n" . 'define(\'_TB_REVISION_\', \'' . $targetRevision . '\');' . "\n";
        }

        @copy($settingsPath, _PS_ROOT_DIR_.'/config/settings.old.php');
        if (! @file_put_contents($settingsPath, $settings)) {
            return ProcessingState::failed($this->l('Could not write new settings file'), 'file = ' . $settingsPath);
        }

        $this->clearCaches();

        return ProcessingState::done();
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function clearCaches()
    {
        Tools::clearSmartyCache();
        Tools::clearXMLCache();
        Media::clearCache();
        Tools::generateIndex();
        PageCache::flush();
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * @return ProcessingState
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Adapter_Exception
     * @throws HTMLPurifier_Exception
     * @throws ReflectionException
     */
    public function migrateDb()
    {
        // Apply retro-compatibility database upgrades.
        $errors = Retrocompatibility::doAllDatabaseUpgrades();
        if ($errors) {
            return ProcessingState::failed($this->l("Retrocompatibility DB update failed"), implode("\n", $errors));
        }

        // database migration
        $objectModelBuilder = new ObjectModelSchemaBuilder();
        $informationSchemaBuilder = new InformationSchemaBuilder();
        $comparator = new DatabaseSchemaComparator();
        $differences = $comparator->getDifferences($informationSchemaBuilder->getSchema(), $objectModelBuilder->getSchema());
        $differences = array_filter($differences, function(SchemaDifference $difference) {
            // At the moment we automatically fix only MissingColumn and MissingTable differences. These are the most
            // important ones - system won't work correctly without it. Also, adding these database objects to database
            // does not pose any threat or issues.
            // In the future, we will probably allow all safe differences to be automatically fixed
            return (
                ($difference instanceof MissingColumn) ||
                ($difference instanceof MissingTable)
            );
        });
        if ($differences) {
            foreach ($differences as $difference) {
                $this->applyDatabaseFix($difference);
            }
            //return sprintf($this->l('Database successfully migrated, %s fixes applied'), count($differences));
        }
        return ProcessingState::done();
    }

    /**
     * @param SchemaDifference $difference
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function applyDatabaseFix(SchemaDifference $difference)
    {
        foreach (static::getDBServers() as $server) {
            // we need to create connection from scratch, because DB::getInstance() doesn't provide mechanism to
            // retrieve connection to specific slave server
            $connection = new DbPDO($server['server'], $server['user'], $server['password'], $server['database']);
            $difference->applyFix($connection);
        }
    }

    /**
     * @return ProcessingState
     * @throws PrestaShopException
     * @throws ReflectionException
     */
    public function initializeCodebase()
    {
        $codeCallback = new CodeCallback();
        $codeCallback->execute(Db::getInstance());
        return ProcessingState::done();
    }

    /**
     * @param string[] $dirs
     * @return ProcessingState
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function cleanup($dirs)
    {
        foreach ($dirs as $dir) {
            Tools::deleteDirectory($dir);
        }

        $this->clearCaches();
        return ProcessingState::done();
    }

    public function prepareResult($versionType, $versionName, Storage $storage)
    {
        $storage->put('result', [
            'versionType' => $versionType,
            'versionName' => $versionName
        ]);
        return ProcessingState::done();
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

    /**
     * @param $changeSet
     * @param $targetFileList
     * @return array
     * @throws Exception
     */
    protected function getFilesToDownload($changeSet, $targetFileList)
    {
        $files = [];
        $toDownload = array_merge($changeSet['change'], $changeSet['add']);
        foreach ($toDownload as $path => $modified) {
            if (!isset($targetFileList[$path])) {
                throw new Exception("File $path not found in file list");
            }
            $file = preg_replace('#^' . preg_quote($this->adminDir . '/') . '#', 'admin/', $path);
            $files[$file] = isset($targetFileList[$path]) ? $targetFileList[$path] : 'n/a';
        }
        return $files;
    }

    /**
     * Return list of files to backup
     *
     * @param $changeSet
     * @return array
     */
    protected function getFilesToBackup($changeSet)
    {
        return array_map([$this, 'fixAdminDirectory'], array_keys(array_filter(array_merge($changeSet['change'],$changeSet['remove']))));
    }

    /**
     * @param $changeSet
     * @return array
     */
    protected function getFilesToDelete($changeSet)
    {
        return array_map(function($file) {
            return $this->fixAdminDirectory($file);
        }, array_keys($changeSet['remove']));
    }

    /**
     * @param $file
     * @return string|string[]|null
     */
    protected function fixAdminDirectory($file)
    {
        return preg_replace('#^admin/#', $this->adminDir . '/', $file);
    }

    /**
     * Returns list of all database servers (both master and slaves)
     *
     * @return array
     */
    protected static function getDBServers()
    {
        // ensure slave server settings are loaded
        Db::getInstance(_PS_USE_SQL_SLAVE_);
        return Db::$_servers;
    }
}
