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

namespace CoreUpdater\Process;


use CoreUpdater\Log\Logger;
use CoreUpdater\Storage\Storage;
use CoreUpdater\Storage\StorageFactory;
use CoreUpdater\Utils;
use Exception;
use PrestaShopDatabaseException;
use PrestaShopException;

abstract class Processor
{
    /**
     * @var StorageFactory
     */
    protected $storageFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Processor constructor.
     * @param Logger $logger
     * @param StorageFactory $storageFactory
     */
    public function __construct(Logger $logger, StorageFactory $storageFactory)
    {
        $this->logger = $logger;
        $this->storageFactory = $storageFactory;
    }

    /**
     * @param array $payload
     * @return string
     * @throws Exception
     */
    public function startProcess($payload)
    {
        $processId = Utils::generateRandomString();
        $this->logger->log("Starting " . $this->getProcessName() . " process with id " . $processId);
        $steps = $this->generateSteps($payload);
        if (! $steps || ! is_array($steps) || count($steps) == 0) {
            throw new Exception("Empty steps");
        }
        $this->logger->log("Process $processId steps: " . json_encode($steps));
        $storage = $this->getStorage($processId);
        $storage->put('processId', $processId);
        $storage->put('steps', $steps);
        $storage->put('currentStep', 0);
        $storage->save();

        return $processId;
    }


    /**
     * @param string $processId
     * @return ProcessingState
     * @throws Exception
     */
    public function process($processId)
    {
        $storage = $this->getStorage($processId);
        if (! $storage->hasKey('processId')) {
            throw new Exception("Process not found: '$processId'");
        }

        $step = (int)$storage->get('currentStep');
        $steps =  $storage->get('steps');
        $totalSteps = (int)count($steps);
        if ($step < $totalSteps) {
            $currentStep = $steps[$step];
            try {
                $this->logger->log("Process $processId: processing step " . json_encode($currentStep));
                $stepState = $this->processStep($processId, $currentStep, $storage);
                $this->logger->log("Process $processId: step " . $currentStep['action'] . " finished with state " . json_encode($stepState->toArray()));
                switch ($stepState->getState()) {
                    case ProcessingState::DONE:
                        $step++;
                        $storage->put('currentStep', $step);
                        if ($step == $totalSteps) {
                            return ProcessingState::done($stepState->getAjax());
                        } else {
                            return ProcessingState::inProgress(ProcessingState::calculateProgress($totalSteps, $step), $stepState->getAjax());
                        }
                    case ProcessingState::FAILED:
                        return ProcessingState::failed($stepState->getError(), $stepState->getDetails());
                    case ProcessingState::IN_PROGRESS:
                        $curProgress = ProcessingState::calculateProgress($totalSteps, $step);
                        $nextProgress = ProcessingState::calculateProgress($totalSteps, $step + 1);
                        $increment = $nextProgress - $curProgress;
                        $stepProgress = $stepState->getProgress();
                        $progress = round($curProgress + ($increment * $stepProgress), 5);
                        return ProcessingState::inProgress($progress);
                    default:
                        throw new Exception("Invalid step state: " . $stepState->getState());
                }
            } catch (Exception $e) {
                return ProcessingState::failed($e->getMessage(), $e->__toString());
            } finally {
                $storage->save();
            }
        }
        return ProcessingState::done();
    }

    /**
     * @param string $processId
     * @return string
     * @throws Exception
     */
    public function describeCurrentStep($processId)
    {
        $storage = $this->getStorage($processId);
        if (! $storage->hasKey('processId')) {
            throw new Exception("Process not found: '$processId'");
        }

        $step = (int)$storage->get('currentStep');
        $steps =  $storage->get('steps');
        $totalSteps = (int)count($steps);
        if ($step < $totalSteps) {
            $currentStep = $steps[$step];
            return $this->describeStep($currentStep, $storage);
        }
        return $this->l("Done");
    }


    /**
     * @param $processId
     * @return Storage
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function getStorage($processId)
    {
        return $this->storageFactory->getStorage( "process-" . $processId);
    }


    /**
     * @param string $key
     * @param array $settings
     * @return mixed
     * @throws Exception
     */
    protected function getParameter($key, $settings)
    {
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }
        throw new Exception("Key $key does not exists in settings");
    }


    /**
     * Get translation for a given text.
     *
     * @param string $string String to translate.
     *
     * @return string Translation.
     */
    protected function l($string)
    {
        return \Translate::getModuleTranslation('coreupdater', $string, 'coreupdater');
    }

    protected abstract function getProcessName();

    /**
     * @param array $payload
     */
    protected abstract function generateSteps($payload);

    /**
     * @param string $processId
     * @param array $step
     * @param Storage $storage
     * @return ProcessingState
     */
    protected abstract function processStep($processId, $step, $storage);

    /**
     * @param array $step
     * @param Storage $storage
     * @return string
     */
    protected abstract function describeStep($step, $storage);


    /**
     * @param string $processId
     * @return mixed
     */
    public abstract function getResult($processId);
}