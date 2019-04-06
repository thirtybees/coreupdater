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

SET NAMES 'utf8mb4';

/* 1.0.1 */
ALTER TABLE `PREFIX_employee`
ALTER COLUMN `last_connection_date`
SET DEFAULT '1970-01-01';
UPDATE `PREFIX_employee`
SET `last_connection_date` = '1970-01-01'
WHERE CAST(`last_connection_date` AS CHAR(20)) = '0000-00-00 00:00:00';

ALTER TABLE `PREFIX_product`
ALTER COLUMN `available_date`
SET DEFAULT '1970-01-01';
UPDATE `PREFIX_product`
SET `available_date` = '1970-01-01'
WHERE CAST(`available_date` AS CHAR(20)) = '0000-00-00 00:00:00';

ALTER TABLE `PREFIX_product_shop`
ALTER COLUMN `available_date`
SET DEFAULT '1970-01-01';
UPDATE `PREFIX_product_shop`
SET `available_date` = '1970-01-01'
WHERE CAST(`available_date` AS CHAR(20)) = '0000-00-00 00:00:00';

ALTER TABLE `PREFIX_product_attribute`
ALTER COLUMN `available_date`
SET DEFAULT '1970-01-01';
UPDATE `PREFIX_product_attribute`
SET `available_date` = '1970-01-01'
WHERE CAST(`available_date` AS CHAR(20)) = '0000-00-00 00:00:00';

ALTER TABLE `PREFIX_product_attribute_shop`
ALTER COLUMN `available_date`
SET DEFAULT '1970-01-01';
UPDATE `PREFIX_product_attribute_shop`
SET `available_date` = '1970-01-01'
WHERE CAST(`available_date` AS CHAR(20)) = '0000-00-00 00:00:00';

DROP TABLE IF EXISTS `PREFIX_url_rewrite`;

INSERT IGNORE INTO `PREFIX_hook` (`name`, `title`)
VALUES ('actionRegisterAutoloader', 'actionRegisterAutoloader');

INSERT IGNORE INTO `PREFIX_hook` (`name`, `title`)
VALUES ('actionRegisterErrorHandlers', 'actionRegisterErrorHandlers');

INSERT IGNORE INTO `PREFIX_hook` (`name`, `title`)
VALUES ('actionRetrieveCurrencyRates', 'actionRetrieveCurrencyRates');

/* 1.0.4 */
ALTER TABLE `PREFIX_referrer` MODIFY `passwd` VARCHAR(60);

/* 1.0.8, without PHP scripts. */
DELETE FROM `PREFIX_configuration`
WHERE `name` LIKE "%CUSTOMCODE%" AND `id_shop` NOT LIKE 0;
