DROP TABLE IF EXISTS `{#}api_keys`;
CREATE TABLE `{#}api_keys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `is_pub` tinyint(1) unsigned DEFAULT NULL,
  `api_key` varchar(32) DEFAULT NULL,
  `description` varchar(100) DEFAULT NULL,
  `ip_access` text,
  `key_methods` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `{#}api_logs`;
CREATE TABLE `{#}api_logs` (
  `key_id` int(10) unsigned DEFAULT NULL,
  `method` varchar(100) DEFAULT NULL,
  `error` tinyint(1) unsigned DEFAULT NULL,
  `date_pub` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `request_time` float unsigned DEFAULT NULL,
  KEY `key_id` (`key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;