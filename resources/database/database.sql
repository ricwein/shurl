-- Create syntax for DATABASE 'shurl'
CREATE DATABASE IF NOT EXISTS `shurl` DEFAULT CHARACTER SET = `utf8mb4`;
USE `shurl`;

-- Create syntax for TABLE 'redirects'
CREATE TABLE IF NOT EXISTS `redirects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url_id` bigint(20) NOT NULL,
  `slug` varchar(64) NOT NULL DEFAULT '',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `valid_from` timestamp NULL DEFAULT NULL,
  `valid_to` timestamp NULL DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `public` tinyint(1) NOT NULL DEFAULT '0',
  `mode` enum('redirect', 'html', 'passthrough') NOT NULL DEFAULT 'redirect',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug_unique` (`slug`),
  KEY `temp` (`created`),
  KEY `search` (`slug`,`enabled`,`valid_to`,`valid_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create syntax for TABLE 'urls'
CREATE TABLE IF NOT EXISTS `urls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url` text NOT NULL,
  `hash` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create syntax for TABLE 'visits'
CREATE TABLE IF NOT EXISTS `visits` (
  `id` bigint(32) unsigned NOT NULL AUTO_INCREMENT,
  `redirect_id` bigint(20) unsigned NOT NULL,
  `visited` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `origin` text NOT NULL,
  `ip` varbinary(32) DEFAULT NULL,
  `user_agent` varchar(128) DEFAULT NULL,
  `referrer` text,
  PRIMARY KEY (`id`),
  KEY `url` (`redirect_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create syntax for TABLE 'version'
CREATE TABLE IF NOT EXISTS `version` (
  `type` enum('last','current') NOT NULL DEFAULT 'current',
  `version` decimal(4,1) DEFAULT NULL,
  PRIMARY KEY (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
