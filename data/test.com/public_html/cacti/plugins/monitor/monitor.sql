ALTER TABLE host ADD monitor char(3) default 'on' not null AFTER disabled;
ALTER TABLE host ADD monitor_text text default '' not null AFTER monitor;
REPLACE INTO `user_auth_realm` VALUES (32, 1);
