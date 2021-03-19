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


class ProcessingState
{
    const IN_PROGRESS = 'IN_PROGRESS';
    const FAILED = 'FAILED';
    const DONE = 'DONE';

    /**
     * @var string
     */
    private $state;

    /**
     * @var array
     */
    private $extra;

    /**
     * ProcessingState constructor.
     *
     * @param string $state
     * @param array $extra
     */
    protected function __construct($state, $extra)
    {
        $this->state = $state;
        $this->extra = $extra;
    }


    /**
     * @param float $progress
     * @param string $ajax
     * @return ProcessingState
     */
    public static function inProgress($progress, $ajax=null)
    {
        return new ProcessingState(static::IN_PROGRESS, static::getProgressExtra($progress, $ajax));
    }

    /**
     * @param null $ajax
     * @return ProcessingState
     */
    public static function done($ajax = null)
    {
        return new ProcessingState(static::DONE, static::getProgressExtra(1.0, $ajax));
    }

    protected static function getProgressExtra($progress, $ajax)
    {
        $data = [
            'progress' => $progress
        ];
        if ($ajax) {
            $data['ajax'] = $ajax;
        }
        return $data;
    }

    /**
     * @param string $error
     * @param string $details
     * @return ProcessingState
     */
    public static function failed($error, $details)
    {
        return new ProcessingState(static::FAILED, [
            'error' => $error,
            'details' => $details
        ]);
    }

    public function hasFinished()
    {
        return in_array($this->state, [static::FAILED, static::DONE]);
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    public function toArray()
    {
        return array_merge($this->extra, [
            'state' => $this->state,
        ]);
    }

    /**
     * @return bool
     */
    public function isDone()
    {
        return $this->state == static::DONE;
    }

    /**
     * @return bool
     */
    public function hasFailed()
    {
        return $this->state == static::FAILED;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->extra['error'];
    }

    /**
     * @return float
     */
    public function getProgress()
    {
        if (isset($this->extra['progress'])) {
            return (float)$this->extra['progress'];
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    public function getDetails()
    {
        return $this->extra['details'];
    }

    /**
     * @return bool
     */
    public function hasAjax()
    {
        return isset($this->extra['ajax']);
    }

    /**
     * @return string
     */
    public function getAjax()
    {
        if (isset($this->extra['ajax'])) {
            return $this->extra['ajax'];
        } else {
            return null;
        }
    }

    /**
     * @param int $totalSteps
     * @param int $step
     * @return float
     */
    public static function calculateProgress($totalSteps, $step)
    {
        if ($step > $totalSteps) {
            return 1.0;
        }
        return round((float)$step / $totalSteps, 5);
    }


}