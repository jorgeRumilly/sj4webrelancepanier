<?php
/**
 * SJ4WEB - Abandoned Cart Reminder (sj4webrelancepanier)
 *
 * Automatically reminds customers about abandoned carts with optional
 * discount codes and conversion tracking.
 *
 * Copyright (C) 2025  SJ4WEB.FR
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Sj4webRelancePanierInstaller
{
    /**
     * Crée les tables du module en base de données
     */
    public static function installDb()
    {
        $queries = [];

        $queries[] = "
            CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."sj4web_relancepanier_campaign` (
              `id_campaign` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(128) NOT NULL,
              `status` ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
            
              `start_time` INT UNSIGNED DEFAULT 0,
              `start_unit` ENUM('minute','hour','day','month') NOT NULL DEFAULT 'hour',
            
              `delay_time1` INT UNSIGNED DEFAULT 0,
              `delay_unit1` ENUM('minute','hour','day','month') NOT NULL DEFAULT 'hour',
              `discount_time1` TINYINT(1) DEFAULT 0,
              `percent_time1` FLOAT DEFAULT 0,
            
              `delay_time2` INT UNSIGNED DEFAULT 0,
              `delay_unit2` ENUM('minute','hour','day','month') NOT NULL DEFAULT 'hour',
              `tolerance_time2` INT UNSIGNED DEFAULT 0,
              `tolerance_unit2` ENUM('minute','hour','day','month') NOT NULL DEFAULT 'hour',
              `discount_time2` TINYINT(1) DEFAULT 0,
              `percent_time2` FLOAT DEFAULT 0,
            
              `delay_time3` INT UNSIGNED DEFAULT 0,
              `delay_unit3` ENUM('minute','hour','day','month') NOT NULL DEFAULT 'hour',
              `tolerance_time3` INT UNSIGNED DEFAULT 0,
              `tolerance_unit3` ENUM('minute','hour','day','month') NOT NULL DEFAULT 'hour',
              `discount_time3` TINYINT(1) DEFAULT 0,
              `percent_time3` FLOAT DEFAULT 0,
            
              `date_add` DATETIME NOT NULL,
              `date_upd` DATETIME NOT NULL,
              PRIMARY KEY (`id_campaign`)
            ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8;";

        $queries[] = "
            CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."sj4web_relancepanier_sent` (
                `id_sent` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_cart` INT UNSIGNED NOT NULL,
                `id_customer` INT UNSIGNED DEFAULT 0,
                `id_campaign` INT UNSIGNED NOT NULL,
                `step` TINYINT(1) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `voucher_code` VARCHAR(64) DEFAULT NULL,
                `sent_at` DATETIME NOT NULL,
                `id_order` INT UNSIGNED DEFAULT NULL,
                `conversion_date` DATETIME DEFAULT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_sent`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        $queries[] = "
            CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."sj4web_relancepanier_unsubscribed` (
              `id_unsubscribed` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `email` VARCHAR(255) NOT NULL DEFAULT '',
              `hash`  VARCHAR(255)  NOT NULL DEFAULT '',
              `unsubscribed_at` DATETIME NOT NULL,
              PRIMARY KEY (`id_unsubscribed`),
              UNIQUE KEY `uniq_email` (`email`),
              KEY `idx_hash` (`hash`)
            ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8;";


        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Supprime les tables du module
     */
    public static function uninstallDb()
    {
        $tables = [
            'sj4web_relancepanier_campaign',
            'sj4web_relancepanier_sent',
            'sj4web_relancepanier_unsubscribed',
        ];

        foreach ($tables as $table) {
            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.$table.'`')) {
                return false;
            }
        }

        return true;
    }
}
