#IfMissingColumn users allowed_to_booked_online
ALTER TABLE `users` ADD COLUMN `allowed_to_booked_online` tinyint default 0;
#EndIf

#IfMissingColumn users vh_credentials
ALTER TABLE `users` ADD COLUMN `vh_credentials` varchar(255) default NULL;
#EndIf

#IfMissingColumn users user_services
#ALTER TABLE `users` ADD COLUMN `user_services` text default NULL;
#EndIf

#IfMissingColumn users specialization
#ALTER TABLE `users` ADD COLUMN `specialization` varchar(255) default NULL;
#EndIf

#IfMissingColumn facility allowed_to_booked_online
ALTER TABLE `facility` ADD COLUMN `allowed_to_booked_online` tinyint default 0;
#EndIf

#IfMissingColumn facility vh_inmoment_location
ALTER TABLE `facility` ADD COLUMN `vh_inmoment_location` varchar(255) default NULL;
#EndIf

#IfMissingColumn users calendar_interval
#ALTER TABLE `users` ADD COLUMN `calendar_interval` varchar(10) default NULL;
#EndIf

#IfNotTable vh_document_form_token
CREATE TABLE `vh_document_form_token` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(40) DEFAULT NULL,
  `token` varchar(128) DEFAULT NULL,
  `expiry` datetime DEFAULT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=revoked,0=not revoked',
  `context` text DEFAULT NULL COMMENT 'context values that change/govern how access token are used',
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
);
#EndIf

#IfMissingColumn vh_document_form_token delete
ALTER TABLE `vh_zoom_webhook_event` ADD COLUMN `participant_uuid` varchar(255) DEFAULT NULL AFTER `user_id`;
#EndIf

#IfMissingColumn vh_document_form_token delete
ALTER TABLE `vh_document_form_token` ADD COLUMN `deleted` tinyint(1) NOT NULL DEFAULT 0 AFTER `expiry`;
#EndIf

#IfNotTable vh_document_form_token
CREATE TABLE `vh_zoom_webhook_event` (
  `meeting_id` varchar(255) NOT NULL,
  `event` varchar(255) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_id` varchar(255) DEFAULT NULL,
  `participant_uuid` varchar(255) DEFAULT NULL,
  `join_time` datetime DEFAULT NULL,
  `leave_time` datetime DEFAULT NULL,
  `event_ts` datetime DEFAULT NULL,
  `payload` text DEFAULT NULL
);
#EndIf

#IfNotTable vh_document_form_token
CREATE TABLE `vh_propio_event` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `meeting_id` varchar(255) NOT NULL,
  `call_id` bigint(20) NOT NULL,
  `statusCallBack` varchar(255) NULL,
  `status` varchar(255) NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable onetime_auth
CREATE TABLE `onetime_auth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` bigint(20) DEFAULT NULL,
  `create_user_id` bigint(20) DEFAULT NULL,
  `context` varchar(64) DEFAULT NULL,
  `access_count` int(11) NOT NULL DEFAULT '0',
  `remote_ip` varchar(32) DEFAULT NULL,
  `onetime_pin` varchar(10) DEFAULT NULL COMMENT 'Max 10 numeric. Default 6',
  `onetime_token` tinytext,
  `redirect_url` tinytext,
  `expires` int(11) DEFAULT NULL,
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_accessed` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`,`onetime_token`(255))
);
#EndIf

#IfNotTable vh_db_triggers
CREATE TABLE `vh_db_triggers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `trigger_name` varchar(40) DEFAULT NULL,
  `trigger_query` longtext DEFAULT NULL,
  `status` varchar(31) DEFAULT NULL,
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_onsite_forms
CREATE TABLE `vh_onsite_forms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` bigint(20) unsigned DEFAULT NULL,
  `form_id` bigint(21) unsigned NOT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `doc_type` varchar(255) NOT NULL,
  `received_date` datetime DEFAULT NULL,
  `reviewed_date` datetime DEFAULT NULL,
  `reviewer` bigint(20) unsigned DEFAULT NULL,
  `denial_reason` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `full_document` longtext,
  `template_data` longtext,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_form_templates
CREATE TABLE `vh_form_templates` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT NULL,
  `template_name` varchar(255) DEFAULT NULL,
  `to_patient` text,
  `status` varchar(31) DEFAULT NULL,
  `template_content` longtext,
  `modified_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_form_packets
CREATE TABLE `vh_form_packets` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email_template` varchar(255) DEFAULT NULL,
  `sms_template` varchar(255) DEFAULT NULL,
  `status` varchar(31) DEFAULT NULL,
  `expire_time` varchar(255) default NULL,
  `modified_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_form_data_log
CREATE TABLE `vh_form_data_log` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_packet_link
CREATE TABLE `vh_packet_link` (
  `packet_id` bigint(21) unsigned NOT NULL,
  `form_id` bigint(21) unsigned NOT NULL
);
#EndIf

#IfNotTable vh_onsite_packets
CREATE TABLE `vh_onsite_packets` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `packet_id` bigint(21) unsigned NOT NULL,
  `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_onetimetoken_form_log
CREATE TABLE `vh_onetimetoken_form_log` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT NULL,
  `onetime_token` text,
  `onetime_token_id` bigint(21) DEFAULT NULL,
  `form_id` bigint(21) DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_form_documents_log
CREATE TABLE `vh_form_documents_log` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(21) DEFAULT NULL,
  `doc_id` bigint(21) DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_form_reminder_log
CREATE TABLE `vh_form_reminder_log` (
  `ref_id` bigint(21) unsigned NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `uniqueid` bigint(20) unsigned DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);
#EndIf

#IfMissingColumn vh_form_templates email_template
ALTER TABLE `vh_form_templates` ADD COLUMN `email_template` varchar(255) default NULL AFTER `template_name`;
#EndIf

#IfMissingColumn vh_form_templates sms_template
ALTER TABLE `vh_form_templates` ADD COLUMN `sms_template` varchar(255) default NULL AFTER `email_template`;
#EndIf

#IfNotTable vh_form_templates deleted
ALTER TABLE `vh_form_templates` ADD COLUMN `expire_time` varchar(255) default NULL AFTER `status`;
#EndIf

#IfNotTable vh_onsite_forms deleted
ALTER TABLE `vh_onsite_forms` ADD COLUMN `deleted` tinyint(1) default 0 AFTER `status`;
#EndIf

#IfNotTable vh_onsite_forms packet_id
ALTER TABLE `vh_onsite_forms` ADD COLUMN `ref_id` bigint(21) DEFAULT 0 AFTER `form_id`;
#EndIf

#IfNotTable vh_onetimetoken_form_log packet_id
ALTER TABLE `vh_onetimetoken_form_log` ADD COLUMN `ref_id` bigint(21) DEFAULT 0 AFTER `form_id`;
#EndIf

#IfNotTable vh_form_data_log created_by
ALTER TABLE `vh_form_data_log` ADD COLUMN `created_by` varchar(50) DEFAULT 0 AFTER `type`;
#EndIf

#IfNotTable vh_propio_event request_data
ALTER TABLE `vh_propio_event` ADD COLUMN `request_data` text DEFAULT NULL AFTER `status`;
#EndIf

#IfNotTable vh_assign_patients
CREATE TABLE `vh_assign_patients` (
  `user_id` bigint(21) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `a_id` bigint(21) DEFAULT NULL
);
#EndIf

#IfNotTable vh_pistorage_preference
CREATE TABLE `vh_pistorage_preference` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(21) DEFAULT NULL,
  `pharmacy` varchar(255) DEFAULT NULL,
  `behavioral_health` varchar(255) DEFAULT NULL,
  `chiropractic_care` varchar(255) DEFAULT NULL,
  `communication` varchar(255) DEFAULT NULL,
  `imaging` varchar(255) DEFAULT NULL,
  `neurology` varchar(255) DEFAULT NULL,
  `ortho` varchar(255) DEFAULT NULL,
  `pain_management` varchar(255) DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_pistorage_preference
CREATE TABLE `vh_recentpatients_history` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(21) DEFAULT NULL,
  `patient_list` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
#EndIf

#IfMissingColumn users vh_credentials
ALTER TABLE `vh_pistorage_preference` ADD COLUMN `insurance_companies_id` bigint(21) DEFAULT NULL AFTER `user_id`;
#EndIf

#IfMissingColumn form_encounter vh_first_esign_datetime
ALTER TABLE `form_encounter` ADD COLUMN `vh_first_esign_datetime` datetime default NULL;
#EndIf

