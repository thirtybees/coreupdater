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

use ObjectModel;
use Translate;
use Db;



/**
 * Class ExtraColumn
 *
 * Represents difference in database key definition
 *
 * @version 1.1.0 Initial version.
 */
class DifferentKey implements SchemaDifference
{
    /**
     * @var TableSchema table
     */
    protected $table;

    /**
     * @var TableKey expected key definition
     */
    protected $key;

    /**
     * @var TableKey current key definition
     */
    protected $currentKey;

    /**
     * DifferentKey constructor.
     *
     * @param TableSchema $table
     * @param TableKey $key
     * @param TableKey $currentKey
     *
     * @version 1.1.0 Initial version.
     */
    public function __construct(TableSchema $table, TableKey $key, TableKey $currentKey)
    {
        $this->table = $table;
        $this->key = $key;
        $this->currentKey = $currentKey;
    }

    /**
     * Return description of the difference.
     *
     * @return string
     *
     * @version 1.1.0 Initial version.
     */
    function describe()
    {
        return sprintf(
            Translate::getModuleTranslation('coreupdater', 'Different [1]%1$s[/1] in table [2]%2$s[/2]', 'coreupdater'),
            $this->key->describeKey(),
            $this->table->getName()
        );
    }

    /**
     * Returns unique identification of this database difference.
     *
     * @return string
     */
    function getUniqueId()
    {
        return get_class($this) . ':' . $this->table->getName() . '.' . $this->key->getName();
    }

    /**
     * This operation is NOT destructive
     *
     * @return bool
     */
    function isDestructive()
    {
        return false;
    }

    /**
     * Returns severity of this difference
     *
     * @return int severity
     */
    function getSeverity()
    {
        return self::SEVERITY_NORMAL;
    }

    /**
     * @return bool
     */
    function isPrimaryKey()
    {
        return $this->currentKey->getType() == ObjectModel::PRIMARY_KEY;
    }

    /**
     * Applies fix to correct this database difference
     *
     * @param Db $connection
     * @return bool
     * @throws \PrestaShopException
     */
    function applyFix(Db $connection)
    {
        if ($this->isPrimaryKey()) {
            $currentColumns = $this->currentKey->getColumns();
            $newColumns = $this->key->getColumns();
            $droppedColumns = array_diff($currentColumns, $newColumns);
            // if some columns were removed from primary key, we have to ensure they are not marked as
            // auto increment
            if ($droppedColumns) {
                $builder = new InformationSchemaBuilder($connection);
                foreach ($droppedColumns as $columnName) {
                    $column = $builder->getCurrentColumn($this->table->getName(), $columnName);
                    if ($column->isAutoIncrement()) {
                        $column->setAutoIncrement(false);
                        $stmt = 'ALTER TABLE `' . bqSQL($this->table->getName()) . '` MODIFY COLUMN ' .$column->getDDLStatement($this->table);
                        $connection->execute($stmt);
                    }
                }
            }
        }
        $stmt = 'ALTER TABLE `' . bqSQL($this->table->getName()) . '` DROP KEY `' . bqSQL($this->currentKey->getName()) . '`, ADD ' .$this->key->getDDLStatement();
        return $connection->execute($stmt);
    }

}

