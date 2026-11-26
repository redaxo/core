INSERT IGNORE INTO `rex_language` VALUES
(1,'de','deutsch',1,1),
(2,'en','english',2,0);

INSERT IGNORE INTO `rex_article` (`id`, `parent_id`, `priority`, `path`, `createdate`, `createuser`, `updatedate`, `updateuser`) VALUES
(1,NULL,1,'|','2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername'),
(2,NULL,1,'|','2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername');

INSERT IGNORE INTO `rex_article_translation` (`article_id`, `language_id`, `name`, `status`, `template`) VALUES
(1,1,'test category',1,'test'),
(1,2,'test category',0,'test'),
(2,1,'test article',0,'test'),
(2,2,'test article',0,'test');

INSERT IGNORE INTO `rex_category` (`id`, `priority`, `createdate`, `createuser`, `updatedate`, `updateuser`) VALUES
(1,1,'2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername');

INSERT IGNORE INTO `rex_category_translation` (`category_id`, `language_id`, `name`) VALUES
(1,1,'test category'),
(1,2,'test category');

INSERT IGNORE INTO `rex_article_slice` (`id`, `article_id`, `language_id`, `content_section_id`, `module`, `revision`, `priority`, `status`, `data`, `createdate`, `createuser`, `updatedate`, `updateuser`) VALUES
(1,1,1,1,'testmodule1',0,1,1,'{"headline":"Welcome","text":"Some text for the slice.","layout":"narrow","image":"redaxo_2018_berlin_sticker.jpg","caption":true,"teasers":[{"title":"First teaser","link":2},{"title":"Second teaser","link":null}]}','2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername'),
(2,2,1,1,'testmodule1',0,1,1,NULL,'2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername'),
(3,1,1,1,'testmodule1',1,1,1,'{"headline":"Welcome","text":"Some text for the slice.","layout":"narrow","image":"redaxo_2018_berlin_sticker.jpg","caption":true,"teasers":[{"title":"First teaser","link":2},{"title":"Second teaser","link":null}]}','2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername');

REPLACE INTO `rex_config` VALUES
('core','lang','"de_de"'),
('core','article_history','true'),
('core','article_work_version','true'),
('core','default_template','"default"');

REPLACE INTO `rex_cronjob` (`name`, `last_run`, `last_success`, `execution_start`) VALUES
('article_history_cleanup','2021-01-01 02:30:00',1,NULL),
('mail_archive_cleanup','2021-01-01 00:00:00',0,NULL);

INSERT IGNORE INTO `rex_media` (`id`, `category_id`, `filetype`, `filename`, `originalname`, `filesize`, `width`, `height`, `meta_ai_generated`, `createdate`, `createuser`, `updatedate`, `updateuser`) VALUES
(1,NULL,'image/jpeg','redaxo_2018_berlin_sticker.jpg','redaxo_2018_berlin_sticker.jpg','78410',1200,900,1,'2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername');

INSERT IGNORE INTO `rex_media_translation` (`media_id`, `language_id`, `title`, `meta_alt_text`) VALUES
(1,1,'Sticker','REDAXO-Sticker auf einem Laptop'),
(1,2,'Sticker','REDAXO sticker on a laptop');



