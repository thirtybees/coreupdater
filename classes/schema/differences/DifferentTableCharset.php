<?php
/**
 * Copyright (C) 2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <contact@thirtybees.com>
 * @copyright 2019 thirty bees
 * @license   Open Software License (OSL 3.0)
 */

namespace CoreUpdater;
use \Translate;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class ExtraColumn
 *
 * Represents difference in table default character set and collation
 *
 * @since 1.1.0
 */
class DifferentTableCharset implements SchemaDifference
{
    private $table;
    private $currentTable;

    public function __construct(TableSchema $table, TableSchema $currentTable)
    {
        $this->table = $table;
        $this->currentTable = $currentTable;
    }

    public function describe()
    {
        return sprintf(
            Translate::getModuleTranslation('coreupdater', 'Table `%1$s` should use character set %2$s instead of %3$s', 'coreupdater'),
            $this->table->getName(),
            $this->table->getCharset()->describe(),
            $this->currentTable->getCharset()->describe()
        );
    }
}
