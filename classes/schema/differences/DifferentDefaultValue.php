<?php
/**
 * Copyright (C) 2019 thirty bees
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
 * @copyright 2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

namespace CoreUpdater;
use \Translate;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class ExtraColumn
 *
 * Difference in column's default value
 *
 * @version 1.1.0 Initial version.
 */
class DifferentDefaultValue implements SchemaDifference
{
    private $table;
    private $column;
    private $currentColumn;

    /**
     * DifferentDefaultValue constructor.
     *
     * @param TableSchema $table
     * @param ColumnSchema $column
     * @param ColumnSchema $currentColumn
     *
     * @version 1.1.0 Initial version.
     */
    public function __construct(TableSchema $table, ColumnSchema $column, ColumnSchema $currentColumn)
    {
        $this->table = $table;
        $this->column = $column;
        $this->currentColumn = $currentColumn;
    }

    /**
     * Return description of the difference.
     *
     * @return string
     *
     * @version 1.1.0 Initial version.
     */
    public function describe()
    {
        $table = $this->table->getName();
        $col = $this->column->getName();
        $value = $this->column->getDefaultValue();
        $currentValue = $this->currentColumn->getDefaultValue();

        return is_null($value)
            ? sprintf(Translate::getModuleTranslation('coreupdater', 'Column `%1$s`.`%2$s` should NOT have default value `%3$s`', 'coreupdater'), $table, $col, $currentValue)
            : sprintf(Translate::getModuleTranslation('coreupdater', 'Column `%1$s`.`%2$s` should have DEFAULT value `%3$s` instead of `%4$s`', 'coreupdater'), $table, $col, $value, $currentValue);
    }
}
