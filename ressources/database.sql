CREATE TABLE `short_redirects` (
  `slug` varchar(64) NOT NULL DEFAULT '',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires` timestamp NULL DEFAULT NULL,
  `hits` bigint(20) unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`slug`),
  KEY `temp` (`date`),
  KEY `search` (`slug`,`enabled`,`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
