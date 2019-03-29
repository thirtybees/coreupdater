<?php
/**
 * Copyright (C) 2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <contact@thirtybees.com>
 * @copyright 2019 thirty bees
 * @license   Open Software License (OSL 3.0)
 */

/**
 * Class DatabaseCharset
 *
 * This class represents character set and collate settings
 *
 * @since 1.1.0
 */
class DatabaseCharset
{
    /**
     * @var string charset
     */
    private $charset = null;

    /**
     * @var string collation
     */
    private $collate = null;

    /**
     * DatabaseCharset constructor.
     * @param string $charset
     * @param string $collate
     */
    public function __construct($charset = null, $collate = null)
    {
        $this->charset = $charset;
        $this->collate = $collate;
    }


    /**
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * @param string $charset
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
    }

    /**
     * @return string
     */
    public function getCollate()
    {
        return $this->collate;
    }

    /**
     * @param string $collate
     */
    public function setCollate($collate)
    {
        $this->collate = $collate;
    }

    /**
     * Returns true if both settings are equal
     *
     * @param DatabaseCharset $other
     * @return bool
     */
    public function equals(DatabaseCharset $other)
    {
        return (
            $this->getCharset() === $other->getCharset()  &&
            $this->getCollate() === $other->getCollate()
        );
    }

    /**
     * Describes character set and collation
     *
     * @return string
     */
    public function describe()
    {
        $charset = $this->getCharset();
        $collate = $this->getCollate();
        if ($charset && $collate) {
            return "$charset/$collate";
        }
        return "NONE";
    }
}