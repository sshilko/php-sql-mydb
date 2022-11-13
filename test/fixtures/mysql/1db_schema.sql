/**
 * This file is part of the sshilko/php-sql-mydb package.
 *
 * (c) Sergei Shilko <contact@sshilko.com>
 *
 * MIT License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.

 @license https://opensource.org/licenses/mit-license.php MIT
 */

/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE = @@TIME_ZONE */;
/*!40103 SET TIME_ZONE = '+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS, UNIQUE_CHECKS = 0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS = 0 */;
/*!40101 SET @OLD_SQL_MODE = @@SQL_MODE, SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES = @@SQL_NOTES, SQL_NOTES = 0 */;

DROP TABLE IF EXISTS `myusers`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `myusers`
(
    `id`   int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(200)     NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 0
  DEFAULT CHARSET = utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `countries`
(
    `id`           int(10) unsigned NOT NULL AUTO_INCREMENT,
    `id_region`    tinyint(4)       NOT NULL DEFAULT '0',
    `code`         varchar(2)       NOT NULL,
    `country`      varchar(80)      NOT NULL,
    `remark`       text             NOT NULL,
    `status`       text             NOT NULL,
    `display_name` varchar(80)      NOT NULL,
    PRIMARY KEY (`id`),
    KEY `id_region` (`id_region`),
    KEY `code` (`code`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 0
  DEFAULT CHARSET = utf8 COMMENT ='http://www.iso.org/iso/iso-3166-1_decoding_table.html';
/*!40101 SET character_set_client = @saved_cs_client */;

DROP TABLE IF EXISTS `myusers_devices`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `myusers_devices`
(
    `id_binary`        binary(12)          NOT NULL,
    `id_user`          int(10) unsigned    NOT NULL,
    `id_realm`         tinyint(1) unsigned NOT NULL     DEFAULT '1',
    `time_saved`       timestamp           NOT NULL     DEFAULT CURRENT_TIMESTAMP,
    `time_last_update` datetime            NOT NULL,
    `device_token`     varchar(255) CHARACTER SET ascii DEFAULT NULL,
    `version`          varchar(9) CHARACTER SET ascii   DEFAULT NULL,
    `sandbox`          tinyint(1) unsigned NOT NULL     DEFAULT '0',
    `handler`          enum ('1','2','3')  NOT NULL     DEFAULT '1',
    `provider`         set ('Sansunk','Hookle','Sany')  NOT NULL DEFAULT 'Sany',
    KEY `id_user` (`id_user`),
    KEY `id_realm4` (`id_realm`),
    KEY `device_token` (`device_token`(5)),
    KEY `id_binary` (`id_binary`(6))
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8
    PARTITION BY LIST (MOD(id_user, 32))
    SUBPARTITION BY KEY (device_token)
    SUBPARTITIONS 2
    (PARTITION p0 VALUES IN (0) ENGINE = InnoDB,
    PARTITION p1 VALUES IN (1) ENGINE = InnoDB,
    PARTITION p2 VALUES IN (2) ENGINE = InnoDB,
    PARTITION p3 VALUES IN (3) ENGINE = InnoDB,
    PARTITION p4 VALUES IN (4) ENGINE = InnoDB,
    PARTITION p5 VALUES IN (5) ENGINE = InnoDB,
    PARTITION p6 VALUES IN (6) ENGINE = InnoDB,
    PARTITION p7 VALUES IN (7) ENGINE = InnoDB,
    PARTITION p8 VALUES IN (8) ENGINE = InnoDB,
    PARTITION p9 VALUES IN (9) ENGINE = InnoDB,
    PARTITION p10 VALUES IN (10) ENGINE = InnoDB,
    PARTITION p11 VALUES IN (11) ENGINE = InnoDB,
    PARTITION p12 VALUES IN (12) ENGINE = InnoDB,
    PARTITION p13 VALUES IN (13) ENGINE = InnoDB,
    PARTITION p14 VALUES IN (14) ENGINE = InnoDB,
    PARTITION p15 VALUES IN (15) ENGINE = InnoDB,
    PARTITION p16 VALUES IN (16) ENGINE = InnoDB,
    PARTITION p17 VALUES IN (17) ENGINE = InnoDB,
    PARTITION p18 VALUES IN (18) ENGINE = InnoDB,
    PARTITION p19 VALUES IN (19) ENGINE = InnoDB,
    PARTITION p20 VALUES IN (20) ENGINE = InnoDB,
    PARTITION p21 VALUES IN (21) ENGINE = InnoDB,
    PARTITION p22 VALUES IN (22) ENGINE = InnoDB,
    PARTITION p23 VALUES IN (23) ENGINE = InnoDB,
    PARTITION p24 VALUES IN (24) ENGINE = InnoDB,
    PARTITION p25 VALUES IN (25) ENGINE = InnoDB,
    PARTITION p26 VALUES IN (26) ENGINE = InnoDB,
    PARTITION p27 VALUES IN (27) ENGINE = InnoDB,
    PARTITION p28 VALUES IN (28) ENGINE = InnoDB,
    PARTITION p29 VALUES IN (29) ENGINE = InnoDB,
    PARTITION p30 VALUES IN (30) ENGINE = InnoDB,
    PARTITION p31 VALUES IN (31) ENGINE = InnoDB);

DROP TABLE IF EXISTS `myusers_languages`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `myusers_languages`
(
    `id`                tinyint(4) unsigned NOT NULL AUTO_INCREMENT,
    `code`              char(7) CHARACTER SET ascii  DEFAULT NULL,
    `language`          varchar(64)         NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 0
  DEFAULT CHARSET = utf8;

DROP TABLE IF EXISTS `mydecimals`;
/*!40101 SET @saved_cs_client = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mydecimals` (
   `id` int(11) NOT NULL AUTO_INCREMENT,
   `cost` decimal(4,2) NOT NULL,
   `comment` varchar(6) DEFAULT NULL,
   PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 0
  DEFAULT CHARSET = ascii;

/*!40101 SET character_set_client = @saved_cs_client */;

