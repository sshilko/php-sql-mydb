-- This file is part of the sshilko/php-sql-mydb package.
--
-- (c) Sergei Shilko <contact@sshilko.com>
--
-- MIT License
--
-- For the full copyright and license information, please view the LICENSE
-- file that was distributed with this source code.
--
-- @license https://opensource.org/licenses/mit-license.php MIT

DROP TABLE IF EXISTS `myusers`;
CREATE TABLE `myusers`
(
    `id`   int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(200)     NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 0
  DEFAULT CHARSET = utf8mb4;

DROP TABLE IF EXISTS `countries`;
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

DROP TABLE IF EXISTS `myusers_devices`;
CREATE TABLE `myusers_devices`
(
    `id_binary`        binary(12)          NOT NULL,
    `id_user`          int(10) unsigned    NOT NULL,
    `id_reality`       tinyint(1) unsigned NOT NULL     DEFAULT '1',
    `time_saved`       timestamp           NOT NULL     DEFAULT CURRENT_TIMESTAMP,
    `time_last_update` datetime            NOT NULL,
    `device_token`     varchar(255) CHARACTER SET ascii DEFAULT NULL,
    `semver`           varchar(9) CHARACTER SET ascii   DEFAULT NULL,
    `sandbox`          tinyint(1) unsigned NOT NULL     DEFAULT '0',
    `handler`          enum ('1','2','3')  NOT NULL     DEFAULT '1',
    `provider`         set ('Sansunk','Hookle','Sany')  NOT NULL DEFAULT 'Sany',
    KEY `id_user` (`id_user`),
    KEY `id_reality` (`id_reality`),
    KEY `device_token` (`device_token`(5)),
    KEY `id_binary` (`id_binary`(6))
) ENGINE = InnoDB DEFAULT CHARSET = utf8
    PARTITION BY LIST (MOD(id_user, 2))
    SUBPARTITION BY KEY (device_token)
    SUBPARTITIONS 2
   (PARTITION p0 VALUES IN (0) ENGINE = InnoDB,
    PARTITION p1 VALUES IN (1) ENGINE = InnoDB);

DROP TABLE IF EXISTS `myusers_languages`;
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
CREATE TABLE `mydecimals` (
   `id` int NOT NULL AUTO_INCREMENT,
   `cost` decimal(4,2) NOT NULL,
   `comment` varchar(6) DEFAULT NULL,
   PRIMARY KEY (`id`)
) ENGINE = InnoDB;

DROP TABLE IF EXISTS `mynames`;
CREATE TABLE `mynames`
(
    `name` varchar(10) NOT NULL,
    UNIQUE KEY (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

DROP TABLE IF EXISTS `mycitynames`;
CREATE TABLE `mycitynames`
(
    `city` varchar(10) NOT NULL,
    `name` varchar(10) NOT NULL,
    PRIMARY KEY (`city`, `name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;