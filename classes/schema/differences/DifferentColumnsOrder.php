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
 * Class DifferentColumnsOrder
 *
 * This difference represents situation when two database tables have the same
 * columns, but in different order.
 *
 * @version 1.1.0 Initial version.
 */
class DifferentColumnsOrder implements SchemaDifference
{
    private $table;
    private $currentTable;

    /**
     * DifferentColumnsOrder constructor.
     *
     * @param TableSchema $table
     * @param TableSchema $currentTable
     *
     * @version 1.1.0 Initial version.
     */
    public function __construct(TableSchema $table, TableSchema $currentTable)
    {
        $this->table = $table;
        $this->currentTable = $currentTable;
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
        return sprintf(
            Translate::getModuleTranslation('coreupdater', 'Columns in table `%1$s` are in wrong order.%2$s %3$s', 'coreupdater'),
            $this->table->getName(),
            "\n      expected: [" . implode(', ', $this->table->getColumnNames()) . "]",
            "\n      current:  [" . implode(', ', $this->currentTable->getColumnNames()) . "]"
        );
    }
}
