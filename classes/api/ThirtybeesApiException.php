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

namespace CoreUpdater\Api;

use Exception;
use Throwable;

class ThirtybeesApiException extends Exception
{
    /**
     * @var array
     */
    private $request;

    /**
     * ThirtybeesApiException constructor.
     * @param $message
     * @param array $request
     * @param Throwable|null $previous
     */
    public function __construct($message, $request, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->request = $request;
    }

    /**
     * @return array
     */
    public function getRequest()
    {
        return $this->request;
    }

}
