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

namespace CoreUpdater;

use \ObjectModel;
use \Translate;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class TableKey
 *
 * This class holds information about specific database key/constraint/index
 *
 * @since 1.1.0
 */
class TableKey
{

    /**
     * @var int Key type, see type constants above
     */
    protected $type;

    /**
     * @var string key name. Primary key has always name 'PRIMARY'
     */
    protected $name;

    /**
     * @var string[] columns this key consists of, order is significant
     */
    protected $columns;

    /**
     * @var int[] $subParts column sub-parts
     *
     * Use this to include only a prefix / portion of column to index, for example
     *
     * KEY `name` (`name`(10))
     *
     * will create index on first 10 characters of name column
     */
    protected $subParts;


    /**
     * TableKey constructor.
     *
     * @param int $type type of database key, see constants above
     * @param string $name name of this key, ignored for primary key
     */
    public function __construct($type, $name)
    {
        $this->type = $type;
        $this->name = $type === ObjectModel::PRIMARY_KEY ? 'PRIMARY' : $name;
        $this->columns = [];
        $this->subParts = [];
    }

    /**
     * Returns name of the key
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Adds column to the key
     *
     * @param string $columnName column name
     * @param int $subPart if not null, then only a portion (prefix) of column will be used for index
     */
    public function addColumn($columnName, $subPart = null)
    {
        $this->columns[] = $columnName;
        $this->subParts[] = $subPart ? (int)$subPart : null;
    }


    /**
     * Returns type of the key
     *
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns column names used by this key
     *
     * @return string[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Returns column subparts (prefixes). If column does not use prefix, then
     * the array will contain null for given position
     *
     * @return int[]
     */
    public function getSubParts()
    {
        return $this->subParts;
    }

    /**
     * Helper method to describe key
     *
     * @return string
     */
    public function describeKey()
    {
        switch ($this->type) {
            case ObjectModel::PRIMARY_KEY:
                return Translate::getModuleTranslation('coreupdater', 'primary key', 'coreupdater');
            case ObjectModel::UNIQUE_KEY:
                return sprintf(Translate::getModuleTranslation('coreupdater', 'unique key `%1$s`', 'coreupdater'), $this->name);
            case ObjectModel::FOREIGN_KEY;
                return sprintf(Translate::getModuleTranslation('coreupdater', 'foreign key `%1$s`', 'coreupdater'), $this->name);
            default:
                return sprintf(Translate::getModuleTranslation('coreupdater', 'key `%1$s`', 'coreupdater'), $this->name);
        }
    }
}
