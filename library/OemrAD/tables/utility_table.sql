#IfNotTable preserve_section_values
CREATE TABLE IF NOT EXISTS `preserve_section_values` (
  `fl_name` varchar(200) NOT NULL,
  `value` LONGTEXT NOT NULL,
  PRIMARY KEY (`fl_name`)
) ENGINE=InnoDB;
#EndIf

#IfNotTable vh_mautic_synccontacts
CREATE TABLE `vh_mautic_synccontacts` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `tablename` varchar(255) DEFAULT NULL,
  `pid` bigint(20) unsigned DEFAULT NULL,
  `uniqueid` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `sent_date` datetime DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_dx_code_alias
CREATE TABLE `vh_dx_code_alias` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) DEFAULT 0,
  `type` varchar(255) DEFAULT NULL,
  `dx_id` bigint(20) DEFAULT 0,
  `alias` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_document_history
CREATE TABLE `vh_document_history` (
  `id` bigint(21) unsigned NOT NULL AUTO_INCREMENT,
  `doc_id` bigint(20) DEFAULT 0,
  `url` varchar(255) DEFAULT NULL,
  `hash` varchar(255) DEFAULT NULL,
  `size` int(11) DEFAULT 0,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable user_provider_groups
CREATE TABLE `user_provider_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `provider_ids` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
#EndIf

#IfNotTable vh_visit_history_packet
CREATE TABLE vh_visit_history_packet (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pid` bigint(20) unsigned DEFAULT NULL,
  `packet_title` VARCHAR(250) NOT NULL,
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_visit_history_packet_items
CREATE TABLE vh_visit_history_packet_items (
  `packet_id` bigint(20) NOT NULL,
  `type` VARCHAR(250) NOT NULL,
  `item_id` bigint(20) NOT NULL
);
#EndIf

#IfNotTable vh_appt_info
CREATE TABLE `vh_appt_info` (
  `name` varchar(255) DEFAULT NULL,
  `percent` varchar(255) DEFAULT NULL,
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);
#EndIf

#IfNotTable vh_inmoment_webservice_notif_log
CREATE TABLE `vh_inmoment_webservice_notif_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` varchar(255) DEFAULT NULL,
  `config_id` varchar(255) DEFAULT NULL,
  `seq` bigint(20) NOT NULL,
  `event_type` varchar(255) DEFAULT '0',
  `tablename` varchar(255) NOT NULL,
  `uniqueid` text,
  `uid` bigint(20) DEFAULT NULL,
  `user_type` varchar(255) DEFAULT NULL,
  `sent` tinyint(1) NOT NULL DEFAULT '0',
  `sent_time` datetime DEFAULT NULL,
  `trigger_time` datetime NOT NULL,
  `time_delay` varchar(255) DEFAULT '0',
  `status` text,
  `request_body` longtext,
  `request_responce` longtext,
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_inmoment_survey_log
CREATE TABLE `vh_inmoment_survey_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `org_id` varchar(255) DEFAULT NULL,
  `response_id` varchar(255) DEFAULT NULL,
  `responce` longtext,
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_wordpress_sync_log
CREATE TABLE `vh_wordpress_sync_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` varchar(255) DEFAULT NULL,
  `config_id` varchar(255) DEFAULT NULL,
  `seq` bigint(20) DEFAULT '0',
  `event_type` varchar(255) DEFAULT '0',
  `mode` varchar(255) DEFAULT NULL,
  `tablename` varchar(255) NOT NULL,
  `uniqueid` varchar(255) NOT NULL,
  `uid` bigint(20) DEFAULT NULL,
  `user_type` varchar(255) DEFAULT NULL,
  `sent` tinyint(1) NOT NULL DEFAULT '0',
  `sent_time` datetime DEFAULT NULL,
  `trigger_time` datetime DEFAULT NULL,
  `time_delay` varchar(255) DEFAULT '0',
  `status` varchar(255) DEFAULT NULL,
  `request_body` longtext,
  `request_responce` longtext,
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfNotTable vh_attorney_portal_config
CREATE TABLE `vh_attorney_portal_config` (
  `abook_id` bigint(20) NOT NULL,
  `portal_access` tinyint(1) NOT NULL DEFAULT '0'
);
#EndIf

#IfMissingColumn vh_attorney_portal_config order_access
ALTER TABLE `vh_attorney_portal_config` ADD COLUMN `order_access` tinyint(1) NOT NULL DEFAULT '0' AFTER `portal_access`;
#EndIf

#IfNotTable vh_external_api_configurations
CREATE TABLE `vh_external_api_configurations` (
  `api_config_id` bigint(20) NOT NULL,
  `auth_type` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `client_id` varchar(255) DEFAULT NULL,
  `client_secret` varchar(255) DEFAULT NULL,
  `api_url` varchar(255) DEFAULT NULL
);
#EndIf

#IfNotTable vh_openemr_calendar_order_link
CREATE TABLE `vh_openemr_calendar_order_link` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `pid` bigint(20) DEFAULT NULL,
  `pc_eid` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfMissingColumn vh_external_api_configurations api_token
ALTER TABLE `vh_external_api_configurations` ADD COLUMN `api_token` varchar(255) AFTER `api_url`;
#EndIf

#IfMissingColumn vh_visit_history_packet_items seq
ALTER TABLE `vh_visit_history_packet_items` ADD COLUMN `seq` INT DEFAULT 0 AFTER `item_id`;
#EndIf

`document_ids` VARCHAR(500) NULL,
  `form_encounter_ids` VARCHAR(500) NULL,
  `patient_id` INT NULL,

#IfMissingColumn user_provider_groups visible_to_all
ALTER TABLE user_provider_groups ADD COLUMN `visible_to_all` INT NULL AFTER `provider_ids`;
#EndIf

#IfMissingColumn patient_data alert_info
ALTER TABLE `patient_data` ADD COLUMN `alert_info` TEXT NOT NULL default '';
INSERT INTO `layout_options` (`form_id`,`field_id`,`group_id`,`title`,`seq`,`data_type`,`uor`,`fld_length`,`max_length`,`list_id`,`titlecols`,`datacols`,`default_value`,`edit_options`,`description`,`fld_rows`) VALUES ('DEM', 'alert_info', '6', 'Alert', 3, 2, 1, 30, 255, '', 1, 1, '', '', 'Alert', 0);
#EndIf

#IfMissingColumn preserve_section_values uid
ALTER TABLE `preserve_section_values` DROP PRIMARY KEY;
ALTER TABLE `preserve_section_values` ADD COLUMN `uid` bigint(20) default NULL FIRST;
#EndIf

#IfMissingColumn users auto_confirm_appt
ALTER TABLE `users` ADD COLUMN `auto_confirm_appt` tinyint(1) default 0 AFTER `physician_type` ;
#EndIf

#IfMissingColumn users allow_create_block
ALTER TABLE `users` ADD COLUMN `allow_create_block` tinyint(1) default 0 AFTER `auto_confirm_appt` ;
#EndIf

#IfMissingColumn facility name1
ALTER TABLE `facility` ADD COLUMN `name1` varchar(255) default NULL AFTER `name` ;
#EndIf

#IfMissingColumn openemr_postcalendar_events_deleted uuid
ALTER TABLE `openemr_postcalendar_events_deleted` ADD uuid binary(16) NULL;
#EndIf

#IfMissingColumn notification_configurations patient_form
ALTER TABLE `notification_configurations` ADD COLUMN `patient_form` varchar(255) default NULL AFTER `notification_template` ;
#EndIf

#IfMissingColumn openemr_postcalendar_events ics_file
ALTER TABLE `openemr_postcalendar_events` ADD COLUMN `ics_file` LONGTEXT NULL AFTER `uuid`;
#EndIf

#IfMissingColumn documents case_id
ALTER TABLE `documents` ADD COLUMN `case_id` bigint(20) default 0 AFTER `imported`;
#EndIf

#IfMissingColumn insurance_companies parent_company
ALTER TABLE `insurance_companies` ADD COLUMN `parent_company` bigint(20) default 0;
#EndIf

#IfMissingColumn notif_log to_direct
ALTER TABLE `notif_log` ADD COLUMN `to_direct` varchar(255) default NULL AFTER `user_type`;
#EndIf

#IfNotTable vh_uber_sync_log
CREATE TABLE `vh_uber_sync_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` varchar(255) DEFAULT NULL,
  `config_id` varchar(255) DEFAULT NULL,
  `seq` bigint(20) DEFAULT '0',
  `event_type` varchar(255) DEFAULT '0',
  `tablename` varchar(255) NOT NULL,
  `uniqueid` varchar(255) NOT NULL,
  `uid` bigint(20) DEFAULT NULL,
  `user_type` varchar(255) DEFAULT NULL,
  `sent` tinyint(1) NOT NULL DEFAULT '0',
  `sent_time` datetime DEFAULT NULL,
  `trigger_time` datetime DEFAULT NULL,
  `time_delay` varchar(255) DEFAULT '0',
  `status` varchar(255) DEFAULT NULL,
  `request_body` longtext,
  `request_responce` longtext,
  `created_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
#EndIf

#IfMissingColumn vh_inmoment_survey_log patientname
alter table vh_inmoment_survey_log
add column patientname  varchar(500),
add column facility varchar(500),
add column provider varchar(500),
add column chartnumber  varchar(500),
add column question1  varchar(500),
add column answer1  varchar(500),
add column question2  varchar(500),
add column answer2  varchar(500),
add column question3  varchar(500),
add column answer3  varchar(500),
add column question4  varchar(500),
add column answer4 varchar(500),
add column question5  varchar(500),
add column answer5  varchar(500),
add column question6 varchar(500),
add column answer6 varchar(500),
add column question7  varchar(500),
add column answer7  varchar(500),
add column question8 varchar(500),
add column answer8 varchar(500),
add column question9  varchar(500),
add column answer9 varchar(500),
add column question10 varchar(500),
add column answer10 varchar(500),
add column question11 varchar(500),
add column answer11 varchar(500),
add column question12 varchar(500),
add column answer12 varchar(500),
add column question13  varchar(500),
add column answer13 varchar(500),
add column question14 varchar(500), 
add column answer14 varchar(500),
add column question15 varchar(500),
add column answer15 varchar(500),
add column question16 varchar(500),
add column answer16  varchar(500),
add column question17 varchar(500),
add column answer17 varchar(500),
add column question18 varchar(500),
add column answer18 varchar(500);
#EndIf