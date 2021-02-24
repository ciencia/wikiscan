-- phpMyAdmin SQL Dump
-- version 4.6.6deb5
-- https://www.phpmyadmin.net/
--
-- Client :  localhost:3306
-- Généré le :  Mer 29 Avril 2020 à 15:52
-- Version du serveur :  5.7.29-0ubuntu0.18.04.1
-- Version de PHP :  7.2.24-0ubuntu0.18.04.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `stats_frwiki`
--

-- --------------------------------------------------------

--
-- Structure de la table `dates`
--

DROP TABLE IF EXISTS `dates`;
CREATE TABLE IF NOT EXISTS `dates` (
  `date` varbinary(8) NOT NULL,
  `type` char(1) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `edits` int(8) UNSIGNED DEFAULT NULL,
  `logs` int(8) UNSIGNED DEFAULT NULL,
  `users` int(8) UNSIGNED DEFAULT NULL,
  `users_edit` int(10) UNSIGNED DEFAULT NULL,
  `ip` int(8) DEFAULT NULL,
  `pages` int(8) UNSIGNED DEFAULT NULL,
  `last_update` binary(14) NOT NULL,
  `refresh` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`date`),
  KEY `last_update` (`last_update`),
  KEY `type_edits` (`type`,`edits`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ranges`
--

DROP TABLE IF EXISTS `ranges`;
CREATE TABLE IF NOT EXISTS `ranges` (
  `range` varchar(64) COLLATE utf8_roman_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_roman_ci DEFAULT NULL,
  `ip` varchar(64) COLLATE utf8_roman_ci NOT NULL,
  `cidr` smallint(6) NOT NULL,
  `prefix1` varchar(10) COLLATE utf8_roman_ci NOT NULL,
  `prefix2` varchar(16) COLLATE utf8_roman_ci NOT NULL,
  `rir` varchar(64) COLLATE utf8_roman_ci NOT NULL,
  `start` varchar(50) COLLATE utf8_roman_ci NOT NULL,
  `end` varchar(50) COLLATE utf8_roman_ci NOT NULL,
  `ips` int(11) NOT NULL DEFAULT '0',
  `edits` int(11) NOT NULL DEFAULT '0',
  `blocked` tinyint(1) NOT NULL DEFAULT '0',
  `flags` int(11) NOT NULL DEFAULT '0',
  `blocked_ips` int(11) NOT NULL DEFAULT '0',
  `range_blocks` int(11) NOT NULL DEFAULT '0',
  `blocks` int(11) NOT NULL DEFAULT '0',
  `block_ips` int(11) NOT NULL DEFAULT '0',
  `unblocks` int(11) NOT NULL DEFAULT '0',
  `proxy` int(11) NOT NULL DEFAULT '0',
  `whois_name` varchar(255) COLLATE utf8_roman_ci DEFAULT NULL,
  `whois_owner` varchar(255) COLLATE utf8_roman_ci DEFAULT NULL,
  `whois_country` varchar(128) COLLATE utf8_roman_ci DEFAULT NULL,
  `whois_cidr` varchar(128) COLLATE utf8_roman_ci DEFAULT NULL,
  `whois_net` varchar(128) COLLATE utf8_roman_ci DEFAULT NULL,
  `whois_check` datetime DEFAULT NULL,
  PRIMARY KEY (`range`),
  KEY `prefix1` (`prefix1`),
  KEY `prefix2` (`prefix2`),
  KEY `whois_check` (`whois_check`),
  KEY `start` (`start`(8)),
  KEY `prefix1_start` (`prefix1`(4),`start`(8)),
  KEY `prefix1_2` (`prefix1`(4),`end`(8)),
  KEY `edits` (`edits`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;

-- --------------------------------------------------------

--
-- Structure de la table `userstats_ip_months`
--

DROP TABLE IF EXISTS `userstats_ip_months`;
CREATE TABLE IF NOT EXISTS `userstats_ip_months` (
  `user` varbinary(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` binary(1) DEFAULT NULL,
  `date` varbinary(8) NOT NULL,
  `date_type` char(1) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `edit` int(8) UNSIGNED DEFAULT NULL,
  `total` int(8) UNSIGNED DEFAULT NULL,
  `reduced` int(8) UNSIGNED DEFAULT NULL,
  `main` int(8) UNSIGNED DEFAULT NULL,
  `talk` int(8) UNSIGNED DEFAULT NULL,
  `meta` int(8) UNSIGNED DEFAULT NULL,
  `annexe` int(8) UNSIGNED DEFAULT NULL,
  `ns_user` int(10) UNSIGNED DEFAULT NULL,
  `ns_file` int(10) UNSIGNED DEFAULT NULL,
  `other` int(8) UNSIGNED DEFAULT NULL,
  `redit` int(8) UNSIGNED DEFAULT NULL,
  `edit_chain` int(8) UNSIGNED DEFAULT NULL,
  `revert` int(8) UNSIGNED DEFAULT NULL,
  `new` int(8) UNSIGNED DEFAULT NULL,
  `new_main` int(8) UNSIGNED DEFAULT NULL,
  `new_redir` int(8) UNSIGNED DEFAULT NULL,
  `new_chain` int(8) UNSIGNED DEFAULT NULL,
  `new_chain_main` int(8) UNSIGNED DEFAULT NULL,
  `hours` int(8) UNSIGNED DEFAULT NULL,
  `days` mediumint(4) UNSIGNED DEFAULT NULL,
  `months` smallint(4) UNSIGNED DEFAULT NULL,
  `years` smallint(2) UNSIGNED DEFAULT NULL,
  `tot_time` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time2` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time3` bigint(20) UNSIGNED DEFAULT NULL,
  `diff` bigint(20) DEFAULT NULL,
  `diff_article_no_rv` bigint(20) DEFAULT NULL,
  `diff_tot` bigint(20) DEFAULT NULL,
  `diff_small` int(8) UNSIGNED DEFAULT NULL,
  `diff_medium` int(8) UNSIGNED DEFAULT NULL,
  `diff_big` int(8) UNSIGNED DEFAULT NULL,
  `tot_size` bigint(20) DEFAULT NULL,
  `log` int(8) UNSIGNED DEFAULT NULL,
  `log_chain` int(8) UNSIGNED DEFAULT NULL,
  `log_sysop` int(8) UNSIGNED DEFAULT NULL,
  `move` int(8) UNSIGNED DEFAULT NULL,
  `filter` int(8) UNSIGNED DEFAULT NULL,
  `patrol` int(8) UNSIGNED DEFAULT NULL,
  `delete` int(8) UNSIGNED DEFAULT NULL,
  `restore` int(8) UNSIGNED DEFAULT NULL,
  `revdelete` int(8) UNSIGNED DEFAULT NULL,
  `protect` int(8) UNSIGNED DEFAULT NULL,
  `unprotect` int(8) UNSIGNED DEFAULT NULL,
  `block` int(8) UNSIGNED DEFAULT NULL,
  `unblock` int(8) UNSIGNED DEFAULT NULL,
  `import` int(8) UNSIGNED DEFAULT NULL,
  `upload` int(8) UNSIGNED DEFAULT NULL,
  `rename` int(8) UNSIGNED DEFAULT NULL,
  `rights` int(8) UNSIGNED DEFAULT NULL,
  `newuser` int(8) UNSIGNED DEFAULT NULL,
  `feedback` int(8) UNSIGNED DEFAULT NULL,
  `last_update` binary(14) NOT NULL,
  PRIMARY KEY (`user`,`date`),
  KEY `type` (`date_type`,`user_type`),
  KEY `date_type` (`date`,`date_type`),
  KEY `edit` (`edit`),
  KEY `type_user` (`date_type`,`user`),
  KEY `total` (`total`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

-- --------------------------------------------------------

--
-- Structure de la table `userstats_ip_tot`
--

DROP TABLE IF EXISTS `userstats_ip_tot`;
CREATE TABLE IF NOT EXISTS `userstats_ip_tot` (
  `user` varbinary(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` binary(1) DEFAULT NULL,
  `date` varbinary(8) NOT NULL,
  `date_type` char(1) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `edit` int(8) UNSIGNED DEFAULT NULL,
  `total` int(8) UNSIGNED DEFAULT NULL,
  `reduced` int(8) UNSIGNED DEFAULT NULL,
  `main` int(8) UNSIGNED DEFAULT NULL,
  `talk` int(8) UNSIGNED DEFAULT NULL,
  `meta` int(8) UNSIGNED DEFAULT NULL,
  `annexe` int(8) UNSIGNED DEFAULT NULL,
  `ns_user` int(10) UNSIGNED DEFAULT NULL,
  `ns_file` int(10) UNSIGNED DEFAULT NULL,
  `other` int(8) UNSIGNED DEFAULT NULL,
  `redit` int(8) UNSIGNED DEFAULT NULL,
  `edit_chain` int(8) UNSIGNED DEFAULT NULL,
  `revert` int(8) UNSIGNED DEFAULT NULL,
  `new` int(8) UNSIGNED DEFAULT NULL,
  `new_main` int(8) UNSIGNED DEFAULT NULL,
  `new_redir` int(8) UNSIGNED DEFAULT NULL,
  `new_chain` int(8) UNSIGNED DEFAULT NULL,
  `new_chain_main` int(8) UNSIGNED DEFAULT NULL,
  `hours` int(8) UNSIGNED DEFAULT NULL,
  `days` mediumint(4) UNSIGNED DEFAULT NULL,
  `months` smallint(4) UNSIGNED DEFAULT NULL,
  `years` smallint(2) UNSIGNED DEFAULT NULL,
  `tot_time` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time2` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time3` bigint(20) UNSIGNED DEFAULT NULL,
  `diff` bigint(20) DEFAULT NULL,
  `diff_article_no_rv` bigint(20) DEFAULT NULL,
  `diff_tot` bigint(20) DEFAULT NULL,
  `diff_small` int(8) UNSIGNED DEFAULT NULL,
  `diff_medium` int(8) UNSIGNED DEFAULT NULL,
  `diff_big` int(8) UNSIGNED DEFAULT NULL,
  `tot_size` bigint(20) DEFAULT NULL,
  `log` int(8) UNSIGNED DEFAULT NULL,
  `log_chain` int(8) UNSIGNED DEFAULT NULL,
  `log_sysop` int(8) UNSIGNED DEFAULT NULL,
  `move` int(8) UNSIGNED DEFAULT NULL,
  `filter` int(8) UNSIGNED DEFAULT NULL,
  `patrol` int(8) UNSIGNED DEFAULT NULL,
  `delete` int(8) UNSIGNED DEFAULT NULL,
  `restore` int(8) UNSIGNED DEFAULT NULL,
  `revdelete` int(8) UNSIGNED DEFAULT NULL,
  `protect` int(8) UNSIGNED DEFAULT NULL,
  `unprotect` int(8) UNSIGNED DEFAULT NULL,
  `block` int(8) UNSIGNED DEFAULT NULL,
  `unblock` int(8) UNSIGNED DEFAULT NULL,
  `import` int(8) UNSIGNED DEFAULT NULL,
  `upload` int(8) UNSIGNED DEFAULT NULL,
  `rename` int(8) UNSIGNED DEFAULT NULL,
  `rights` int(8) UNSIGNED DEFAULT NULL,
  `newuser` int(8) UNSIGNED DEFAULT NULL,
  `feedback` int(8) UNSIGNED DEFAULT NULL,
  `last_update` binary(14) NOT NULL,
  PRIMARY KEY (`user`,`date`),
  KEY `type` (`date_type`,`user_type`),
  KEY `date_type` (`date`,`date_type`),
  KEY `edit` (`edit`),
  KEY `type_user` (`date_type`,`user`),
  KEY `total` (`total`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

-- --------------------------------------------------------

--
-- Structure de la table `userstats_ip_years`
--

DROP TABLE IF EXISTS `userstats_ip_years`;
CREATE TABLE IF NOT EXISTS `userstats_ip_years` (
  `user` varbinary(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` binary(1) DEFAULT NULL,
  `date` varbinary(8) NOT NULL,
  `date_type` char(1) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `edit` int(8) UNSIGNED DEFAULT NULL,
  `total` int(8) UNSIGNED DEFAULT NULL,
  `reduced` int(8) UNSIGNED DEFAULT NULL,
  `main` int(8) UNSIGNED DEFAULT NULL,
  `talk` int(8) UNSIGNED DEFAULT NULL,
  `meta` int(8) UNSIGNED DEFAULT NULL,
  `annexe` int(8) UNSIGNED DEFAULT NULL,
  `ns_user` int(10) UNSIGNED DEFAULT NULL,
  `ns_file` int(10) UNSIGNED DEFAULT NULL,
  `other` int(8) UNSIGNED DEFAULT NULL,
  `redit` int(8) UNSIGNED DEFAULT NULL,
  `edit_chain` int(8) UNSIGNED DEFAULT NULL,
  `revert` int(8) UNSIGNED DEFAULT NULL,
  `new` int(8) UNSIGNED DEFAULT NULL,
  `new_main` int(8) UNSIGNED DEFAULT NULL,
  `new_redir` int(8) UNSIGNED DEFAULT NULL,
  `new_chain` int(8) UNSIGNED DEFAULT NULL,
  `new_chain_main` int(8) UNSIGNED DEFAULT NULL,
  `hours` int(8) UNSIGNED DEFAULT NULL,
  `days` mediumint(4) UNSIGNED DEFAULT NULL,
  `months` smallint(4) UNSIGNED DEFAULT NULL,
  `years` smallint(2) UNSIGNED DEFAULT NULL,
  `tot_time` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time2` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time3` bigint(20) UNSIGNED DEFAULT NULL,
  `diff` bigint(20) DEFAULT NULL,
  `diff_article_no_rv` bigint(20) DEFAULT NULL,
  `diff_tot` bigint(20) DEFAULT NULL,
  `diff_small` int(8) UNSIGNED DEFAULT NULL,
  `diff_medium` int(8) UNSIGNED DEFAULT NULL,
  `diff_big` int(8) UNSIGNED DEFAULT NULL,
  `tot_size` bigint(20) DEFAULT NULL,
  `log` int(8) UNSIGNED DEFAULT NULL,
  `log_chain` int(8) UNSIGNED DEFAULT NULL,
  `log_sysop` int(8) UNSIGNED DEFAULT NULL,
  `move` int(8) UNSIGNED DEFAULT NULL,
  `filter` int(8) UNSIGNED DEFAULT NULL,
  `patrol` int(8) UNSIGNED DEFAULT NULL,
  `delete` int(8) UNSIGNED DEFAULT NULL,
  `restore` int(8) UNSIGNED DEFAULT NULL,
  `revdelete` int(8) UNSIGNED DEFAULT NULL,
  `protect` int(8) UNSIGNED DEFAULT NULL,
  `unprotect` int(8) UNSIGNED DEFAULT NULL,
  `block` int(8) UNSIGNED DEFAULT NULL,
  `unblock` int(8) UNSIGNED DEFAULT NULL,
  `import` int(8) UNSIGNED DEFAULT NULL,
  `upload` int(8) UNSIGNED DEFAULT NULL,
  `rename` int(8) UNSIGNED DEFAULT NULL,
  `rights` int(8) UNSIGNED DEFAULT NULL,
  `newuser` int(8) UNSIGNED DEFAULT NULL,
  `feedback` int(8) UNSIGNED DEFAULT NULL,
  `last_update` binary(14) NOT NULL,
  PRIMARY KEY (`user`,`date`),
  KEY `type` (`date_type`,`user_type`),
  KEY `date_type` (`date`,`date_type`),
  KEY `edit` (`edit`),
  KEY `type_user` (`date_type`,`user`),
  KEY `total` (`total`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

-- --------------------------------------------------------

--
-- Structure de la table `userstats_months`
--

DROP TABLE IF EXISTS `userstats_months`;
CREATE TABLE IF NOT EXISTS `userstats_months` (
  `user` varbinary(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` binary(1) DEFAULT NULL,
  `date` varbinary(8) NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `month` tinyint(3) UNSIGNED NOT NULL,
  `edit` int(8) UNSIGNED DEFAULT NULL,
  `total` int(8) UNSIGNED DEFAULT NULL,
  `reduced` int(8) UNSIGNED DEFAULT NULL,
  `main` int(8) UNSIGNED DEFAULT NULL,
  `talk` int(8) UNSIGNED DEFAULT NULL,
  `meta` int(8) UNSIGNED DEFAULT NULL,
  `annexe` int(8) UNSIGNED DEFAULT NULL,
  `ns_user` int(10) UNSIGNED DEFAULT NULL,
  `ns_file` int(10) UNSIGNED DEFAULT NULL,
  `other` int(8) UNSIGNED DEFAULT NULL,
  `article` int(10) UNSIGNED DEFAULT NULL,
  `redit` int(8) UNSIGNED DEFAULT NULL,
  `edit_chain` int(8) UNSIGNED DEFAULT NULL,
  `revert` int(8) UNSIGNED DEFAULT NULL,
  `new` int(8) UNSIGNED DEFAULT NULL,
  `new_main` int(8) UNSIGNED DEFAULT NULL,
  `new_redir` int(8) UNSIGNED DEFAULT NULL,
  `new_chain` int(8) UNSIGNED DEFAULT NULL,
  `new_chain_main` int(8) UNSIGNED DEFAULT NULL,
  `hours` int(8) UNSIGNED DEFAULT NULL,
  `days` mediumint(4) UNSIGNED DEFAULT NULL,
  `months` smallint(4) UNSIGNED DEFAULT NULL,
  `years` smallint(2) UNSIGNED DEFAULT NULL,
  `tot_time` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time2` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time3` bigint(20) UNSIGNED DEFAULT NULL,
  `diff` bigint(20) DEFAULT NULL,
  `diff_article_no_rv` bigint(20) DEFAULT NULL,
  `diff_tot` bigint(20) DEFAULT NULL,
  `diff_small` int(8) UNSIGNED DEFAULT NULL,
  `diff_medium` int(8) UNSIGNED DEFAULT NULL,
  `diff_big` int(8) UNSIGNED DEFAULT NULL,
  `tot_size` bigint(20) DEFAULT NULL,
  `log` int(8) UNSIGNED DEFAULT NULL,
  `log_chain` int(8) UNSIGNED DEFAULT NULL,
  `log_sysop` int(8) UNSIGNED DEFAULT NULL,
  `move` int(8) UNSIGNED DEFAULT NULL,
  `filter` int(8) UNSIGNED DEFAULT NULL,
  `patrol` int(8) UNSIGNED DEFAULT NULL,
  `delete` int(8) UNSIGNED DEFAULT NULL,
  `restore` int(8) UNSIGNED DEFAULT NULL,
  `revdelete` int(8) UNSIGNED DEFAULT NULL,
  `protect` int(8) UNSIGNED DEFAULT NULL,
  `unprotect` int(8) UNSIGNED DEFAULT NULL,
  `block` int(8) UNSIGNED DEFAULT NULL,
  `unblock` int(8) UNSIGNED DEFAULT NULL,
  `import` int(8) UNSIGNED DEFAULT NULL,
  `upload` int(8) UNSIGNED DEFAULT NULL,
  `rename` int(8) UNSIGNED DEFAULT NULL,
  `rights` int(8) UNSIGNED DEFAULT NULL,
  `newuser` int(8) UNSIGNED DEFAULT NULL,
  `feedback` int(8) UNSIGNED DEFAULT NULL,
  `last_update` binary(14) NOT NULL,
  PRIMARY KEY (`year`,`user_id`,`month`),
  KEY `date_total` (`date`,`total`),
  KEY `user_id` (`user_id`),
  KEY `date_last_update` (`date`,`last_update`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

-- --------------------------------------------------------

--
-- Structure de la table `userstats_tot`
--

DROP TABLE IF EXISTS `userstats_tot`;
CREATE TABLE IF NOT EXISTS `userstats_tot` (
  `user` varbinary(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` binary(1) DEFAULT NULL,
  `date` varbinary(8) NOT NULL,
  `edit` int(8) UNSIGNED DEFAULT NULL,
  `total` int(8) UNSIGNED DEFAULT NULL,
  `reduced` int(8) UNSIGNED DEFAULT NULL,
  `main` int(8) UNSIGNED DEFAULT NULL,
  `talk` int(8) UNSIGNED DEFAULT NULL,
  `meta` int(8) UNSIGNED DEFAULT NULL,
  `annexe` int(8) UNSIGNED DEFAULT NULL,
  `ns_user` int(10) UNSIGNED DEFAULT NULL,
  `ns_file` int(10) UNSIGNED DEFAULT NULL,
  `other` int(8) UNSIGNED DEFAULT NULL,
  `article` int(10) UNSIGNED DEFAULT NULL,
  `redit` int(8) UNSIGNED DEFAULT NULL,
  `edit_chain` int(8) UNSIGNED DEFAULT NULL,
  `revert` int(8) UNSIGNED DEFAULT NULL,
  `new` int(8) UNSIGNED DEFAULT NULL,
  `new_main` int(8) UNSIGNED DEFAULT NULL,
  `new_redir` int(8) UNSIGNED DEFAULT NULL,
  `new_chain` int(8) UNSIGNED DEFAULT NULL,
  `new_chain_main` int(8) UNSIGNED DEFAULT NULL,
  `hours` int(8) UNSIGNED DEFAULT NULL,
  `days` mediumint(4) UNSIGNED DEFAULT NULL,
  `months` smallint(4) UNSIGNED DEFAULT NULL,
  `years` smallint(2) UNSIGNED DEFAULT NULL,
  `tot_time` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time2` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time3` bigint(20) UNSIGNED DEFAULT NULL,
  `diff` bigint(20) DEFAULT NULL,
  `diff_article_no_rv` bigint(20) DEFAULT NULL,
  `diff_tot` bigint(20) DEFAULT NULL,
  `diff_small` int(8) UNSIGNED DEFAULT NULL,
  `diff_medium` int(8) UNSIGNED DEFAULT NULL,
  `diff_big` int(8) UNSIGNED DEFAULT NULL,
  `tot_size` bigint(20) DEFAULT NULL,
  `log` int(8) UNSIGNED DEFAULT NULL,
  `log_chain` int(8) UNSIGNED DEFAULT NULL,
  `log_sysop` int(8) UNSIGNED DEFAULT NULL,
  `move` int(8) UNSIGNED DEFAULT NULL,
  `filter` int(8) UNSIGNED DEFAULT NULL,
  `patrol` int(8) UNSIGNED DEFAULT NULL,
  `delete` int(8) UNSIGNED DEFAULT NULL,
  `restore` int(8) UNSIGNED DEFAULT NULL,
  `revdelete` int(8) UNSIGNED DEFAULT NULL,
  `protect` int(8) UNSIGNED DEFAULT NULL,
  `unprotect` int(8) UNSIGNED DEFAULT NULL,
  `block` int(8) UNSIGNED DEFAULT NULL,
  `unblock` int(8) UNSIGNED DEFAULT NULL,
  `import` int(8) UNSIGNED DEFAULT NULL,
  `upload` int(8) UNSIGNED DEFAULT NULL,
  `rename` int(8) UNSIGNED DEFAULT NULL,
  `rights` int(8) UNSIGNED DEFAULT NULL,
  `newuser` int(8) UNSIGNED DEFAULT NULL,
  `feedback` int(8) UNSIGNED DEFAULT NULL,
  `last_update` binary(14) NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `edit` (`edit`),
  KEY `total` (`total`),
  KEY `user` (`user`),
  KEY `last_update` (`last_update`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

-- --------------------------------------------------------

--
-- Structure de la table `userstats_years`
--

DROP TABLE IF EXISTS `userstats_years`;
CREATE TABLE IF NOT EXISTS `userstats_years` (
  `user` varbinary(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` binary(1) DEFAULT NULL,
  `date` varbinary(8) NOT NULL,
  `edit` int(8) UNSIGNED DEFAULT NULL,
  `total` int(8) UNSIGNED DEFAULT NULL,
  `reduced` int(8) UNSIGNED DEFAULT NULL,
  `main` int(8) UNSIGNED DEFAULT NULL,
  `talk` int(8) UNSIGNED DEFAULT NULL,
  `meta` int(8) UNSIGNED DEFAULT NULL,
  `annexe` int(8) UNSIGNED DEFAULT NULL,
  `ns_user` int(10) UNSIGNED DEFAULT NULL,
  `ns_file` int(10) UNSIGNED DEFAULT NULL,
  `other` int(8) UNSIGNED DEFAULT NULL,
  `article` int(10) UNSIGNED DEFAULT NULL,
  `redit` int(8) UNSIGNED DEFAULT NULL,
  `edit_chain` int(8) UNSIGNED DEFAULT NULL,
  `revert` int(8) UNSIGNED DEFAULT NULL,
  `new` int(8) UNSIGNED DEFAULT NULL,
  `new_main` int(8) UNSIGNED DEFAULT NULL,
  `new_redir` int(8) UNSIGNED DEFAULT NULL,
  `new_chain` int(8) UNSIGNED DEFAULT NULL,
  `new_chain_main` int(8) UNSIGNED DEFAULT NULL,
  `hours` int(8) UNSIGNED DEFAULT NULL,
  `days` mediumint(4) UNSIGNED DEFAULT NULL,
  `months` smallint(4) UNSIGNED DEFAULT NULL,
  `years` smallint(2) UNSIGNED DEFAULT NULL,
  `tot_time` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time2` bigint(20) UNSIGNED DEFAULT NULL,
  `tot_time3` bigint(20) UNSIGNED DEFAULT NULL,
  `diff` bigint(20) DEFAULT NULL,
  `diff_article_no_rv` bigint(20) DEFAULT NULL,
  `diff_tot` bigint(20) DEFAULT NULL,
  `diff_small` int(8) UNSIGNED DEFAULT NULL,
  `diff_medium` int(8) UNSIGNED DEFAULT NULL,
  `diff_big` int(8) UNSIGNED DEFAULT NULL,
  `tot_size` bigint(20) DEFAULT NULL,
  `log` int(8) UNSIGNED DEFAULT NULL,
  `log_chain` int(8) UNSIGNED DEFAULT NULL,
  `log_sysop` int(8) UNSIGNED DEFAULT NULL,
  `move` int(8) UNSIGNED DEFAULT NULL,
  `filter` int(8) UNSIGNED DEFAULT NULL,
  `patrol` int(8) UNSIGNED DEFAULT NULL,
  `delete` int(8) UNSIGNED DEFAULT NULL,
  `restore` int(8) UNSIGNED DEFAULT NULL,
  `revdelete` int(8) UNSIGNED DEFAULT NULL,
  `protect` int(8) UNSIGNED DEFAULT NULL,
  `unprotect` int(8) UNSIGNED DEFAULT NULL,
  `block` int(8) UNSIGNED DEFAULT NULL,
  `unblock` int(8) UNSIGNED DEFAULT NULL,
  `import` int(8) UNSIGNED DEFAULT NULL,
  `upload` int(8) UNSIGNED DEFAULT NULL,
  `rename` int(8) UNSIGNED DEFAULT NULL,
  `rights` int(8) UNSIGNED DEFAULT NULL,
  `newuser` int(8) UNSIGNED DEFAULT NULL,
  `feedback` int(8) UNSIGNED DEFAULT NULL,
  `last_update` binary(14) NOT NULL,
  PRIMARY KEY (`user_id`,`date`),
  KEY `date_total` (`date`,`total`),
  KEY `date_last_update` (`date`,`last_update`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

-- --------------------------------------------------------

--
-- Structure de la table `user_groups`
--

DROP TABLE IF EXISTS `user_groups`;
CREATE TABLE IF NOT EXISTS `user_groups` (
  `ug_user` int(10) UNSIGNED DEFAULT NULL,
  `user_name` varbinary(255) NOT NULL,
  `ug_group` varbinary(32) NOT NULL DEFAULT '',
  UNIQUE KEY `ug_user_group` (`user_name`,`ug_group`),
  KEY `ug_group` (`ug_group`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

-- --------------------------------------------------------

--
-- Structure de la table `site_stats`
--

CREATE TABLE IF NOT EXISTS `site_stats` (
  `disabled` tinyint(4) NOT NULL DEFAULT '0',
  `score` decimal(8,5) UNSIGNED DEFAULT NULL,
  `total_rev` int(10) UNSIGNED DEFAULT NULL,
  `total_log` int(10) UNSIGNED DEFAULT NULL,
  `total_archive` int(10) UNSIGNED DEFAULT NULL,
  `total_user` int(10) UNSIGNED DEFAULT NULL,
  `total_rev_user` int(10) UNSIGNED DEFAULT NULL,
  `total_rev_log_user` int(10) UNSIGNED DEFAULT NULL,
  `total_page` int(10) UNSIGNED DEFAULT NULL,
  `total_redirect` int(10) UNSIGNED DEFAULT NULL,
  `total_article` int(10) UNSIGNED DEFAULT NULL,
  `total_file` int(10) UNSIGNED DEFAULT NULL,
  `last_count` datetime DEFAULT NULL,
  `last_stats` datetime DEFAULT NULL,
  `duration_stats` int(11) DEFAULT NULL,
  `last_live` datetime DEFAULT NULL,
  `duration_live` int(11) DEFAULT NULL,
  `last_sum` datetime DEFAULT NULL,
  `duration_sum` int(11) DEFAULT NULL,
  `last_groups` datetime DEFAULT NULL,
  `last_misc` datetime DEFAULT NULL,
  `users` int(10) UNSIGNED DEFAULT NULL,
  `users_edit` int(10) UNSIGNED DEFAULT NULL,
  `data` mediumblob
) ENGINE=InnoDB DEFAULT CHARSET=binary ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `wiki_bots`
--

CREATE TABLE IF NOT EXISTS `wiki_bots` (
  `user_name` varbinary(255) NOT NULL,
  PRIMARY KEY (`user_name`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
