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

namespace CoreUpdater;

use CoreUpdater\Log\Logger;
use Exception;

class ErrorHandler
{

    /** @var bool */
    private $registered;

    /** @var callable original error handler */
    private $orig;

    /** @var number */
    private $depth = 0;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param callable $callable
     * @param array $parameters
     * @return mixed
     */
    public function handleErrors($callable, $parameters=[])
    {
        $this->setUp();
        try {
            return call_user_func_array($callable, $parameters);
        } catch(Exception $exception) {
            $this->logError($exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString());
        } finally {
            $this->tearDown();
        }
        return false;
    }

    /**
     * @param string $name
     */
    private function setUp()
    {
        if (!$this->registered) {
            $this->registered = true;
            register_shutdown_function([$this, "onShutdown"]);
        }

        if (! $this->orig) {
            $this->orig = set_error_handler([$this, 'errorHandler']);
        }
        $this->depth++;
    }

    /**
     *
     */
    private function tearDown()
    {
        $this->depth--;
        if (! $this->depth) {
            set_error_handler($this->orig);
            $this->orig = null;
        }
    }

    /**
     * @param $message
     * @param $file
     * @param $line
     * @param null $stacktrace
     */
    private function logError($message, $file, $line, $stacktrace=null)
    {
        $content = "$message in $file at line $line\n";
        if ($stacktrace) {
            $content .= $stacktrace . "\n";
        }
        $this->logger->error($content);
    }

    /**
     * @param $errno
     * @param $errstr
     * @param $file
     * @param $line
     * @return bool
     */
    public function errorHandler($errno, $errstr, $file, $line)
    {
        if (static::isFatalError($errno)) {
            $this->logError($errstr, $file, $line);
        }
        return false;
    }

    /**
     *
     */
    public function onShutdown()
    {
        $error = error_get_last();
        if ($error && static::isFatalError($error['type'])) {
            $this->logError($error['message'],  $error['file'], $error['line']);
            if (! headers_sent()) {
                die(json_encode([
                    'success' => false,
                    'error' => [
                        'message' => $error['message'],
                        'details' => $error['file'] . ' at line ' . $error['line']
                    ]
                ]));
            }
        }
    }

    public static function isFatalError($errno)
    {
        return (
            $errno === E_USER_ERROR ||
            $errno === E_ERROR ||
            $errno === E_CORE_ERROR ||
            $errno === E_COMPILE_ERROR ||
            $errno === E_RECOVERABLE_ERROR
        );
    }
}
