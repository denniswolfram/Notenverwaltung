<?php

class InitPlugin extends Migration
{
    public function up()
    {
        $db = DBManager::get();

        // Tabelle: fsz_notenverwaltung_config
        $db->exec("CREATE TABLE IF NOT EXISTS `fsz_notenverwaltung_config` (
            `id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
            `seminar_id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
            `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `seminar_id` (`seminar_id`, `name`(50))
        ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC");

        // Tabelle: fsz_notenverwaltung_noten
        $db->exec("CREATE TABLE IF NOT EXISTS `fsz_notenverwaltung_noten` (
            `user_id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
            `config_id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
            `value` varchar(3) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
            `changed_by_user_id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
            `mkdate` int(10) UNSIGNED NOT NULL,
            `chdate` int(10) UNSIGNED NOT NULL,
            PRIMARY KEY (`config_id`, `user_id`)
        ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC");

        // Indexe aktualisieren
        try {
            $db->exec("ALTER TABLE `fsz_notenverwaltung_config`
                DROP INDEX `seminar_id`,
                ADD INDEX `seminar_id` (`seminar_id`, `name`(50))");
        } catch (PDOException $e) {
            error_log("Error updating index for `fsz_notenverwaltung_config`: " . $e->getMessage());
        }

        try {
            $db->exec("ALTER TABLE `fsz_notenverwaltung_noten`
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (`config_id`, `user_id`)");
        } catch (PDOException $e) {
            error_log("Error updating primary key for `fsz_notenverwaltung_noten`: " . $e->getMessage());
        }

        // Plugin-Daten aktualisieren
        $db->exec("UPDATE `plugins`
            SET `pluginclassname` = 'FszNotenVerwaltungPlugin',
                `pluginname` = 'FszNotenVerwaltungPlugin'
            WHERE `pluginclassname` LIKE 'FszNotenVerwaltungPlugin'");
    }

    public function down()
    {
        // Rollback-Logik implementieren, falls erforderlich
        // Beispiel:
        // $db = DBManager::get();
        // $db->exec("DROP TABLE IF EXISTS `fsz_notenverwaltung_config`");
        // $db->exec("DROP TABLE IF EXISTS `fsz_notenverwaltung_noten`");
    }
}
