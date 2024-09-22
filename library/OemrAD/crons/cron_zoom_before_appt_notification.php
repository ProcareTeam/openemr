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

use Vitalhealthcare\OpenEMR\Modules\Generic\Util\ZoomUtils;
use OpenEMR\OemrAd\ActionEvent;

$templateId = "";
$timeInterval = -5;
$apptCat = array_map('trim', explode(",", $GLOBALS['zoom_appt_category']));
$notifyConfig = array(
	array("type" => "email", "template" => "Zoom_Email_5_Minute_Warning"),
	array("type" => "sms", "template" => "Zoom_SMS_5_Minute_Warning")
);


function cron_getNotificationData()
{
	global $timeInterval, $apptCat;

	$apptItems = array();
	$result = sqlStatementNoLog("SELECT TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) as eventDateTime, ope.pc_eid as uniqueid, 'openemr_postcalendar_events' as tablename, zae.m_id, pd.hipaa_allowemail, pd.email_direct, pd.hipaa_allowsms, pd.phone_cell, CONCAT(CONCAT_WS(' ', IF(LENGTH(pd.fname),pd.fname,NULL), IF(LENGTH(pd.lname),pd.lname,NULL))) as patient_name, ope.* from openemr_postcalendar_events ope join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid join zoom_appointment_events zae on zae.pc_eid = ope.pc_eid join patient_data pd on pd.pid = ope.pc_pid where (DATE_ADD(TIMESTAMP(ope.pc_eventDate, ope.pc_startTime), INTERVAL " . $timeInterval . " MINUTE) <= NOW() and TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) >= NOW()) and opc.pc_catid in ('" . implode("','", $apptCat) . "') and ope.pc_apptstatus not in ('x', '%' , '?')  and not exists (select varl.* from vh_action_reminder_log varl where varl.tablename='openemr_postcalendar_events' and varl.config_id = 'zoom_before_appt_notification'and date(varl.created_time)=date(now()) and varl.uniqueid = ope.pc_eid) order by ope.pc_eid desc;", array());

	while ($appt_row = sqlFetchArray($result)) {
		
		// Check user in waiting room
		$countWaitingRoom = ZoomUtils::zoomGetUserCountOnWaitingRoom($appt_row['m_id']);
		if(isset($countWaitingRoom['waiting_user_count']) && $countWaitingRoom['waiting_user_count'] == "0") {
			$apptItems[] = $appt_row;
		}
	}

	return $apptItems;
}

$totalEmailCount = 0;
$totalSentEmailCount = 0;

$totalSmsCount = 0;
$totalSentSmsCount = 0;

try {

  	$path_parts = pathinfo($_SERVER['SCRIPT_NAME']);
	$filename = isset($path_parts['filename']) ? $path_parts['filename'] . ".lock" : "";

	if(empty($filename)) {
		throw new Exception("Empty filename");
	}

	// Check lock
	$cron_lock = fopen(dirname( __FILE__, 1 ) . "/" . $filename, "w+");
	if (!flock($cron_lock, LOCK_EX | LOCK_NB)) {
		throw new Exception("Unable to acquire lock");
	}

	$db_notification_data = cron_getNotificationData();

	foreach ($db_notification_data as $dbItem) {
		$app_id = isset($dbItem['pc_eid']) ? $dbItem['pc_eid'] : "";
		$pc_pid = isset($dbItem['pc_pid']) ? $dbItem['pc_pid'] : "";
		$event_datetime = isset($dbItem['eventDateTime']) ? $dbItem['eventDateTime'] : "";

		if(!empty($pc_pid)) {
			$pData = sqlQuery("SELECT pd.title, pd.fname, pd.mname, pd.lname FROM patient_data as pd WHERE pid = ?",array($pc_pid));

			// preformat commonly used data elements
			$pat_name = ($pData['title'])? $pData['title'] . " " : "";
			$pat_name .= ($pData['fname'])? $pData['fname'] . " " : "";
			$pat_name .= ($pData['mname'])? substr($pData['mname'],0,1).". " : "";
			$pat_name .= ($pData['lname'])? $pData['lname'] . " " : "";
		}

		foreach ($notifyConfig as $config) {
			if($config['type'] == "email") {

				$totalEmailCount++;

				try {
					$message_text = @ActionEvent::getMsgContent($config['type'], $config['template'], $pc_pid, $event_datetime, $app_id);

					$messaging_enabled = ($dbItem['hipaa_allowemail'] != 'YES' || (empty($dbItem['email']) && !$GLOBALS['wmt::use_email_direct']) || (empty($dbItem['email_direct']) && $GLOBALS['wmt::use_email_direct'])) ? true : false;

					if($messaging_enabled === true) {
						throw new \Exception("Messaging Disable");
					}

					$preparedData = array(
						'event_id' => "zoom_appt_notification",
						'config_id' => "zoom_before_appt_notification",
						'msg_type' => $config['type'],
						'template_id' => $config['template'],
						'message' => $message_text,
						'to_send' => $dbItem['email_direct'],
						'operation_type' => 2,
						'event_type' => '1',
						'tablename' => $dbItem['tablename'],
						'uniqueid' => $dbItem['uniqueid'],
						'uid' => 0,
						'user_type' => "Cron",
						'sent' => '1',
						'sent_time' => date('Y-m-d i:h:s'),
						'trigger_time' => date('Y-m-d i:h:s'),
						'time_delay' => 0
					);

					$eventData = array(
						'email_from' => isset($GLOBALS['EMAIL_SEND_FROM']) ? $GLOBALS['EMAIL_SEND_FROM'] : 'PATIENT SUPPORT',
						'email_patient' => isset($pat_name) ? $pat_name : ' ',
						'template_id' => $config['template'],
						'to_send' => $dbItem['email_direct'],
						'email_patient' => $dbItem['patient_name'],
						'pid' => $pc_pid,
						'message' => $message_text,
						'event_datetime' => $event_datetime,
						'appt_id' => $app_id
					);

					$itemStatus = @ActionEvent::sendEmail($eventData);
					if($itemStatus !== true) {
						throw new \Exception($itemStatus);
					}

					@ActionEvent::savePreparedData($preparedData);
					$totalSentEmailCount++;

				} catch(Exception $e) {
  					echo 'Error: ' .$e->getMessage();
				}

			} else if($config['type'] == "sms") {
				$totalSmsCount++;

				try {
					$message_text = @ActionEvent::getMsgContent($config['type'], $config['template'], $pc_id, $event_datetime, $app_id);

					$pat_phone = isset($dbItem['phone_cell']) && !empty($dbItem['phone_cell']) ? preg_replace('/[^0-9]/', '', $dbItem['phone_cell']) : "";

					$isEnable = $dbItem['hipaa_allowsms'] != 'YES' || empty($dbItem['phone_cell']) ? true : false;

					if($isEnable !== false) {
						throw new \Exception("Messaging Disable");
					}

					$preparedData = array(
							'event_id' => "zoom_appt_notification",
							'config_id' => "zoom_before_appt_notification",
							'msg_type' => $config['type'],
							'template_id' => $config['template'],
							'message' => $message_text,
							'to_send' => $pat_phone,
							'operation_type' => 2,
							'event_type' => '1',
							'tablename' => $dbItem['tablename'],
							'uniqueid' => $dbItem['uniqueid'],
							'uid' => 0,
							'user_type' => "Cron",
							'sent' => '1',
							'sent_time' => date('Y-m-d i:h:s'),
							'trigger_time' => date('Y-m-d i:h:s'),
							'time_delay' => 0
						);

					$eventData = array(
						'template_id' => $config['template'],
						'to_send' => $pat_phone,
						'to_patient' => $dbItem['patient_name'],
						'pid' => $pc_pid,
						'message' => $message_text
					);

					$itemStatus = @ActionEvent::sendSMS($eventData);
					if($itemStatus !== true) {
						throw new \Exception($itemStatus);
					}

					@ActionEvent::savePreparedData($preparedData);
					$totalSentSmsCount++;

				} catch(Exception $e) {
  					echo 'Error: ' .$e->getMessage();
				}
			}
		}
	}

	// Close file
	fclose($cron_lock);
} catch(Exception $e) {
  	echo 'Error: ' .$e->getMessage();
}

echo "\n\nTotal Email Item: " . $totalEmailCount . "\tTotal Sent Item: " . $totalSentEmailCount;
echo "\nTotal SMS Item: " . $totalSmsCount . "\tTotal Sent Item: " . $totalSentSmsCount . "\n";