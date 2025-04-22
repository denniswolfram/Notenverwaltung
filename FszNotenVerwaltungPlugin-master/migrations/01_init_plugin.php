<?php
class InitPlugin extends Migration
{
    function up()
    {
        $db = DBManager::get();
        $db->exec("CREATE TABLE IF NOT EXISTS `fsz_notenverwaltung_config` (
                  `id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                  `seminar_id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `seminar_id` (`seminar_id`,`name`(50))
                ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC");

        $db->exec("CREATE TABLE IF NOT EXISTS `fsz_notenverwaltung_noten` (
                  `user_id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                  `config_id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                  `value` varchar(3) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                  `changed_by_user_id` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                  `mkdate` int(10) UNSIGNED NOT NULL,
                  `chdate` int(10) UNSIGNED NOT NULL,
                  PRIMARY KEY (`config_id`, `user_id`)
                ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC");

        try {
            $db->exec("ALTER TABLE `fsz_notenverwaltung_config` DROP INDEX `seminar_id`, ADD INDEX `seminar_id` (`seminar_id`, `name`(50))");
        } catch (PDOException $e) {}
        try {
            $db->exec("ALTER TABLE `fsz_notenverwaltung_noten` DROP PRIMARY KEY, ADD PRIMARY KEY (`config_id`, `user_id`)");
        } catch (PDOException $e) {}

        $db->exec("UPDATE `plugins` SET `pluginclassname` = 'FszNotenVerwaltungPlugin', `pluginname` = 'FszNotenVerwaltungPlugin' WHERE `pluginclassname` LIKE 'FszNotenVerwaltungPlugin'");
    }

    function down()
    {

    }
}
