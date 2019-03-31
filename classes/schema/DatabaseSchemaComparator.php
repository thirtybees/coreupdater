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
    private $ignoreTables = [];

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
        return $differences;
    }
}