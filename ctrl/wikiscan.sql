-- phpMyAdmin SQL Dump
-- version 4.6.6deb5
-- https://www.phpmyadmin.net/
--
-- Client :  localhost:3306
-- Généré le :  Mer 18 Septembre 2019 à 16:50
-- Version du serveur :  5.7.27-0ubuntu0.18.04.1
-- Version de PHP :  7.2.19-0ubuntu0.18.04.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `wikiscan`
--

-- --------------------------------------------------------

--
-- Structure de la table `global_bots`
--

CREATE TABLE IF NOT EXISTS `global_bots` (
  `user_name` varbinary(255) NOT NULL,
  `projects` mediumint(8) UNSIGNED NOT NULL,
  PRIMARY KEY (`user_name`),
  KEY `projects` (`projects`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

-- --------------------------------------------------------

--
-- Structure de la table `sites`
--

CREATE TABLE IF NOT EXISTS `sites` (
  `site_id` int(10) UNSIGNED NOT NULL,
  `site_global_key` varbinary(32) NOT NULL,
  `site_type` varbinary(32) NOT NULL,
  `site_group` varbinary(32) NOT NULL,
  `site_source` varbinary(32) NOT NULL,
  `site_language` varbinary(32) NOT NULL,
  `site_protocol` varbinary(32) NOT NULL,
  `site_domain` varbinary(255) NOT NULL,
  `site_data` blob NOT NULL,
  `site_forward` tinyint(1) NOT NULL,
  `site_config` blob NOT NULL,
  `site_host` varbinary(255) NOT NULL,
  PRIMARY KEY (`site_id`),
  UNIQUE KEY `site_global_key` (`site_global_key`),
  KEY `site_group` (`site_group`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

-- --------------------------------------------------------

--
-- Structure de la table `sites_stats`
--

CREATE TABLE IF NOT EXISTS `sites_stats` (
  `site_id` int(10) UNSIGNED NOT NULL,
  `site_global_key` varbinary(32) NOT NULL,
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
  `data` mediumblob,
  PRIMARY KEY (`site_id`),
  UNIQUE KEY `site_global_key` (`site_global_key`),
  KEY `score` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=binary ROW_FORMAT=DYNAMIC;

-- --------------------------------------------------------

--
-- Structure de la table `wiki_bots`
--

CREATE TABLE IF NOT EXISTS `wiki_bots` (
  `wiki` varbinary(255) NOT NULL,
  `user_name` varbinary(255) NOT NULL,
  PRIMARY KEY (`wiki`,`user_name`)
) ENGINE=InnoDB DEFAULT CHARSET=binary;

--
-- Déclencheurs `wiki_bots`
--
DELIMITER $$
CREATE TRIGGER `wiki_bots_delete` AFTER DELETE ON `wiki_bots` FOR EACH ROW BEGIN
    update global_bots set projects=projects-1 where user_name=old.user_name;
    delete from global_bots where user_name=old.user_name and projects=0;
  END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `wiki_bots_insert` AFTER INSERT ON `wiki_bots` FOR EACH ROW BEGIN
    insert into `global_bots` (user_name, projects) values (new.user_name, 1) on duplicate key update projects=projects+1;
  END
$$
DELIMITER ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
