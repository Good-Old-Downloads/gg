CREATE TABLE IF NOT EXISTS `files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(10) unsigned DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '0',
  `type` enum('GAME','GOODIES','PATCHES') NOT NULL,
  `size` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `name` (`name`),
  KEY `game_id` (`game_id`),
  KEY `size` (`size`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `games` (
  `id` int(11) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `indev` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `new` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `updated` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `last_upload` int(11) unsigned NOT NULL DEFAULT 0,
  `last_update` int(11) unsigned NOT NULL DEFAULT 0,
  `has_background` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `has_thumbnail` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `slug` varchar(255) NOT NULL,
  `slug_folder` varchar(255) DEFAULT NULL,
  `url` varchar(2083) DEFAULT NULL,
  `release_date` int(11) unsigned DEFAULT NULL,
  `developer` varchar(255) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `hidden` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `uploading` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `queued` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_slug` (`slug`),
  UNIQUE KEY `unq_slug_folder` (`slug_folder`),
  KEY `cat` (`category`),
  KEY `pub` (`publisher`),
  KEY `dev` (`developer`),
  KEY `slug` (`slug`),
  KEY `rlsdate` (`release_date`),
  KEY `hidden` (`hidden`),
  KEY `indev` (`indev`),
  KEY `new` (`new`),
  KEY `updated` (`updated`),
  KEY `title` (`title`),
  KEY `last_upload` (`last_upload`),
  KEY `date_added` (`last_update`),
  KEY `slug_folder` (`slug_folder`),
  KEY `uploading` (`uploading`),
  KEY `queued` (`queued`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `hosters` (
  `id` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `order` int(2) unsigned NOT NULL,
  `icon_html` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `hosters` (`id`, `name`, `order`, `icon_html`) VALUES
  ('1fichier', '1fichier', 14, '<img src="/static/img/hoster_logos/1fichier.svg" class="fac-hoster">'),
  ('filecloud', 'filecloud.io', 8, '<i class="fas fa-fw fa-cloud"></i>'),
  ('filescdn', 'Filescdn', 9, '<img src="/static/img/hoster_logos/filescdn.svg" class="fac-hoster">'),
  ('gdrive', 'Google Drive', 2, '<i class="fab fa-fw fa-google-drive"></i>'),
  ('gdrive_folder', 'Google Drive', 1, '<i class="fab fa-fw fa-google-drive"></i>'),
  ('letsupload', 'LetsUpload', 3, '<img src="/static/img/hoster_logos/letsupload.svg" class="fac-hoster">'),
  ('megaup', 'MegaUp', 5, '<img src="/static/img/hoster_logos/megaup.svg" class="fac-hoster">'),
  ('openload', 'Openload', 7, '<img src="/static/img/hoster_logos/openload.svg" class="fac-hoster">'),
  ('shareonline_biz', 'Share-Online', 13, '<img src="/static/img/hoster_logos/shareonline.svg" class="fac-hoster">'),
  ('uploaded', 'Uploaded.net', 12, '<img src="/static/img/hoster_logos/uploaded.svg" class="fac-hoster">'),
  ('uploadhaven', 'UploadHaven', 4, '<img src="/static/img/hoster_logos/uploadhaven.svg" class="fac-hoster">'),
  ('uptobox', 'UptoBox', 11, '<img src="/static/img/hoster_logos/uptobox.svg" class="fac-hoster">'),
  ('userscloud', 'Userscloud', 6, '<i class="fa-stack fa-fw"><i class="fas fa-square fa-stack-2x"></i><i class="fas fa-star fa-stack-1x fa-inverse"></i></i>'),
  ('zippyshare', 'Zippyshare', 10, '<img src="/static/img/hoster_logos/zippyshare.svg" class="fac-hoster">');


CREATE TABLE IF NOT EXISTS `links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` int(10) unsigned DEFAULT 0,
  `link` varchar(255) NOT NULL DEFAULT '0',
  `link_safe` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` enum('GAME','GOODIES','PATCHES') NOT NULL,
  `host` varchar(50) DEFAULT NULL,
  `hidden` tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `game_id` (`game_id`),
  KEY `link` (`link`),
  KEY `name` (`name`),
  KEY `host` (`host`),
  KEY `hidden` (`hidden`),
  KEY `link_safe` (`link_safe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `value` text DEFAULT NULL,
  `date` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `site` (
  `name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `votes` (
  `uid` varbinary(16) NOT NULL,
  `game_id` int(11) NOT NULL,
  PRIMARY KEY (`uid`,`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;