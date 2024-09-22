#IfNotTable vh_chat_conversations
CREATE TABLE `vh_chat_conversations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `pid` bigint(20) DEFAULT NULL,
  `uid` bigint(20) DEFAULT NULL,
  `conversation_id` varchar(255) DEFAULT NULL,
  `submit_time` varchar(45) DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_chat_form
CREATE TABLE `vh_chat_form` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `msg_id` bigint(20) DEFAULT NULL,
  `conversation_id` bigint(20) DEFAULT NULL,
  `uid` bigint(20) DEFAULT 0,
  `direction` varchar(50) DEFAULT NULL,
  `status_code` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sender_url` text DEFAULT NULL,
  `msg_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creation_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable user_notification
CREATE TABLE `user_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `notification` varchar(255) DEFAULT NULL,
  `user_status` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable user_notification
INSERT INTO `user_notification` VALUES (2,1,'CHAT');
#EndIf

#IfNotTable vh_chat_conversations conversation_rating
ALTER TABLE `vh_chat_conversations` ADD COLUMN `conversation_rating` varchar(50) DEFAULT NULL AFTER `conversation_id`;
#EndIf

#IfNotTable vh_chat_conversations chat_status
ALTER TABLE `vh_chat_conversations` ADD COLUMN `chat_status` TINYINT(2) DEFAULT NULL AFTER `conversation_rating`;
#EndIf