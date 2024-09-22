<?php

// larry :: hack add for command line version
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
$_SERVER['SERVER_NAME'] = 'localhost';
$_GET['site'] = 'default';
$backpic = "";

// email notification
$ignoreAuth = 1;
require_once(dirname( __FILE__, 2 ) . "/interface/globals.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\ActionEvent;

try {

	$res12 = sqlStatement("select count(*)/(select count(*) from openemr_postcalendar_events zaee ,users uu, openemr_postcalendar_categories catt where zaee.pc_aid =uu.id and zaee.pc_catid = catt.pc_catid and exists (SELECT 1 from list_options lo where lo.option_id = uu.taxonomy and lo.list_id = 'taxonomy' and lo.title = 'Chiropractic') and zaee.pc_eventdate between DATE_ADD(CURDATE(), INTERVAL(-WEEKDAY(CURDATE())) DAY) and DATE(CURDATE() + INTERVAL (7 - DAYOFWEEK(CURDATE())) DAY) and zaee.pc_facility=zae.pc_facility and UPPER(catt.pc_catname) not like '%NEW%')*100 as percent,f.name from openemr_postcalendar_events zae ,users u,facility f, openemr_postcalendar_categories cat where zae.pc_aid =u.id and zae.pc_facility=f.id and zae.pc_catid = cat.pc_catid and UPPER(cat.pc_catname) not like '%NEW%' 
and exists (SELECT 1 from list_options lo where lo.option_id = u.taxonomy and lo.list_id = 'taxonomy' and lo.title = 'Chiropractic') and zae.pc_eventdate between date_add(DATE(CURDATE() + INTERVAL (7 - DAYOFWEEK(CURDATE())) DAY), interval 2 day)  and  date_add(DATE(CURDATE() + INTERVAL (7 - DAYOFWEEK(CURDATE())) DAY), interval 8 day) group by f.name,zae.pc_facility");

	// delete all records from appt info table
	sqlStatementNoLog("DELETE FROM `vh_appt_info`");

	if(sqlNumRows($res12) > 0) {

	    while ($result4 = sqlFetchArray($res12)) { 

	    	// Insert data
			sqlInsert("INSERT INTO `vh_appt_info` ( name, percent ) VALUES (?, ?) ", array($result4['name'], round($result4['percent'], 2) ?? 0));
	    }
	}

} catch(Exception $e) {
  	echo 'Error: ' .$e->getMessage();
}