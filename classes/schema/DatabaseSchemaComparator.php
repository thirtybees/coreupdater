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

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class DatabaseSchemaComparator
 *
 * This class can compare two DatabaseSchema objects and return array of SchemaDifference[] describing differences
 * between these two databases.
 *
 * Result can be used to report on changes, and also to apply fixes and migrate database to expected state
 *
 * @since 1.1.0
 */
class DatabaseSchemaComparator
{
    /**
     * @var string[] array of table names that should be ignored during database comparison
     */
    protected $ignoreTables = [];

    /**
     * DatabaseSchemaComparator constructor.
     *
     * @param array $parameters comparison parameters. Use this to adjust comparison
     *
     * Currently supported parameters:
     *   - ignoreTables: string[] list of database tables to be ignored during comparison
     */
    public function __construct($parameters = [])
    {
        if (isset($parameters['ignoreTables'])) {
            $this->ignoreTables = array_map(function($table) {
                return _DB_PREFIX_ . $table;
            }, $parameters['ignoreTables']);
        }
    }

    /**
     * Returns differences between two database schemas
     *
     * @param DatabaseSchema $currentSchema
     * @param DatabaseSchema $targetSchema
     * @return SchemaDifference[]
     */
    public function getDifferences(DatabaseSchema $currentSchema, DatabaseSchema $targetSchema)
    {
        $differences = [];
        $tables = $this->getTables($targetSchema);

        foreach ($tables as $table) {
            if (!$currentSchema->hasTable($table->getName())) {
                $differences[] = new MissingTable($table);
            } else {
                $currentTable = $currentSchema->getTable($table->getName());
                $differences = array_merge($differences, $this->getTableDifferences($currentTable, $table));
            }
        }

        foreach ($this->getTables($currentSchema) as $table) {
            if (! $targetSchema->hasTable($table->getName())) {
                $differences[] = new ExtraTable($table);
            }
        }

        return $differences;
    }

    /**
     * Compares two database tables and returns differences between them
     *
     * @param TableSchema $currentTable current table
     * @param TableSchema $targetTable  target table
     * @return SchemaDifference[]
     */
    public function getTableDifferences(TableSchema $currentTable, TableSchema $targetTable)
    {
        $differences = [];
        $haveSameColumns = true;

        // 1) detect missing columns
        foreach ($this->getMissingColumns($currentTable, $targetTable) as $column) {
            $differences[] = new MissingColumn($targetTable, $column);
            $haveSameColumns = false;
        }

        // 2) find column differences
        foreach ($targetTable->getColumns() as $targetColumn) {
            $currentColumn = $currentTable->getColumn($targetColumn->getName());
            if ($currentColumn) {
                $differences = array_merge($differences, $this->getColumnDifferences($targetTable, $currentColumn, $targetColumn));
            }
        }

        // 3) detect extra columns
        foreach ($this->getMissingColumns($targetTable, $currentTable) as $column) {
            $differences[] = new ExtraColumn($currentTable, $column);
            $haveSameColumns = false;
        }

        // test columns order only when both tables contains the same columns
        if ($haveSameColumns) {
            if ($targetTable->getColumnNames() !== $currentTable->getColumnNames()) {
                $differences[] = new DifferentColumnsOrder($targetTable, $currentTable);
            }
        }

        return $differences;
    }

    /**
     * Compares two database columns and return list of differences
     *
     * @param TableSchema $table
     * @param ColumnSchema $current
     * @param ColumnSchema $target
     * @return SchemaDifference[]
     */
    public function getColumnDifferences(TableSchema $table, ColumnSchema $current, ColumnSchema $target)
    {
        $differences = [];
        return $differences;
    }

    /**
     * Returns sorted list of database tables without tables listed in $ignoreTables property
     *
     * @param DatabaseSchema $schema database schema
     * @return TableSchema[] list of tables
     */
    protected function getTables(DatabaseSchema $schema)
    {
        $tables = array_filter($schema->getTables(), function(TableSchema $table) {
            if ($this->ignoreTables) {
                return ! in_array($table->getName(), $this->ignoreTables);
            }
            return true;
        });
        usort($tables, function(TableSchema $a, TableSchema $b) {
            return strcmp($a->getName(), $b->getName());
        });
        return $tables;
    }

    /**
     * Returns list of columns that are present int $currentTable but are missing in $targetTable
     *
     * @param TableSchema $currentTable
     * @param TableSchema $targetTable
     * @return ColumnSchema[]
     */
    protected function getMissingColumns(TableSchema $currentTable, TableSchema $targetTable)
    {
        $missingColumns = [];
        foreach ($targetTable->getColumns() as $column) {
            if (! $currentTable->hasColumn($column->getName())) {
                $missingColumns[] = $column;
            }
        }
        return $missingColumns;
    }
}