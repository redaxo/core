## Redaxo Database Dump Version 6
## Prefix rex_

INSERT IGNORE INTO `rex_article` VALUES
(1,1,0,'test category','test category',1,1,1,'|',1,'test',1,'2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername'),
(2,2,0,'test article','',0,0,1,'|',0,'test',1,'2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername');

INSERT IGNORE INTO `rex_article_slice` VALUES
(1,1,1,1,'testmodule1',0,1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername'),
(2,2,1,1,'testmodule1',0,1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername'),
(3,1,1,1,'testmodule1',1,1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername');

INSERT IGNORE INTO `rex_clang` VALUES
(1,'de','deutsch',1,1),
(2,'en','english',2,0);

REPLACE INTO `rex_config` VALUES
('core','article_history','true'),
('core','article_work_version','true'),
('core','default_template','"default"');

INSERT IGNORE INTO `rex_cronjob` VALUES
(1,'Artikel-Status',NULL,'Redaxo\\Core\\Cronjob\\Type\\ArticleStatusType',NULL,'{\"minutes\":[0],\"hours\":[0],\"days\":\"all\",\"weekdays\":\"all\",\"months\":\"all\"}',NULL,'|frontend|backend|script|',1,NULL,0,'2022-07-17 21:08:53','admin','2022-07-18 00:03:20','admin'),
(2,'Tabellen-Optimierung',NULL,'Redaxo\\Core\\Cronjob\\Type\\OptimizeTableType',NULL,'{\"minutes\":[0],\"hours\":[0],\"days\":\"all\",\"weekdays\":\"all\",\"months\":\"all\"}',NULL,'|frontend|backend|script|',0,NULL,0,'2022-07-17 21:08:54','admin','2022-07-17 21:18:38','admin');

INSERT IGNORE INTO `rex_media` VALUES
(1,0,'image/jpeg','redaxo_2018_berlin_sticker.jpg','redaxo_2018_berlin_sticker.jpg','78410',1200,900,'Sticker','2021-01-01 11:37:20','myusername','2021-01-01 11:37:20','myusername');



