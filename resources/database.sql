-- Create syntax for TABLE 'redirects'
CREATE TABLE `redirects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url_id` bigint(20) NOT NULL,
  `slug` varchar(64) NOT NULL DEFAULT '',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires` timestamp NULL DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug_unique` (`slug`),
  KEY `temp` (`created`),
  KEY `search` (`slug`,`enabled`,`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create syntax for TABLE 'urls'
CREATE TABLE `urls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url` text NOT NULL,
  `hash` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create syntax for TABLE 'visits'
CREATE TABLE `visits` (
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
