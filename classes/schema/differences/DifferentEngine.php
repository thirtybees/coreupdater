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
 * Represents different table engine (InnoDB / MyISAM)
 *
 * @since 1.1.0
 */
class DifferentEngine implements SchemaDifference
{
    private $table;
    private $currentEngine;

    /**
     * DifferentEngine constructor.
     *
     * @param TableSchema $table
     * @param string $currentEngine
     *
     * @since 1.1.0
     */
    public function __construct(TableSchema $table, $currentEngine)
    {
        $this->table = $table;
        $this->currentEngine = $currentEngine;
    }

    /**
     * Return description of the difference.
     *
     * @return string
     *
     * @since 1.1.0
     */
    function describe()
    {
        return sprintf(
            Translate::getModuleTranslation('coreupdater', 'Table `%1$s` use `%2$s` database engine instead of `%3$s`', 'coreupdater'),
            $this->table->getName(),
            $this->currentEngine,
            $this->table->getEngine()
        );
    }
}