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
 * Class MissingTable
 *
 * Missing table in target database
 *
 * @since 1.1.0
 */
class MissingTable implements SchemaDifference
{
    private $table;

    public function __construct(TableSchema $table)
    {
        $this->table = $table;
    }

    function describe()
    {
        return sprintf(Translate::getModuleTranslation('coreupdater', 'Table `%1$s` does not exist', 'coreupdater'), $this->table->getName());
    }
}