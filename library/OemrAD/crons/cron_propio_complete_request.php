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

use Vitalhealthcare\OpenEMR\Modules\Generic\Util\PropioUtils;
use OpenEMR\OemrAd\ActionEvent;

$timeInterval = 30;

function cron_Data()
{
	global $timeInterval;

	$apptItems = array();
	$result = sqlStatementNoLog("SELECT vpe.id as propio_id, vpe.status, TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) as eventDateTime, DATE_ADD(TIMESTAMP(ope.pc_eventDate, ope.pc_startTime), INTERVAL (pc_duration / 60) MINUTE) as eventEndDateTime,   ope.pc_eid as uniqueid, 'openemr_postcalendar_events' as tablename, ope.* from openemr_postcalendar_events ope join zoom_appointment_events zae on zae.pc_eid = ope.pc_eid join vh_propio_event vpe on vpe.meeting_id = zae.m_id where DATE_ADD(TIMESTAMP(ope.pc_eventDate, ope.pc_startTime), INTERVAL ((ope.pc_duration / 60) + " . $timeInterval . ") MINUTE) <= now() and vpe.status  = 'Requested' and ope.pc_duration > 0 and not exists (select varl.* from vh_action_reminder_log varl where varl.tablename='openemr_postcalendar_events' and varl.config_id = 'propio_complete_request'and date(varl.created_time)=date(now()) and varl.uniqueid = ope.pc_eid) order by ope.pc_eid desc", array());

	while ($appt_row = sqlFetchArray($result)) {
		
		$apptItems[] = $appt_row;
	}

	return $apptItems;
}

$totalCount = 0;
$totalCompletedRequestCount = 0;


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

	$db_data =cron_Data();

	foreach ($db_data as $dbItem) {
		$app_id = isset($dbItem['pc_eid']) ? $dbItem['pc_eid'] : "";
		$pc_pid = isset($dbItem['pc_pid']) ? $dbItem['pc_pid'] : "";
		$event_datetime = isset($dbItem['eventDateTime']) ? $dbItem['eventDateTime'] : "";
		$propio_id = isset($dbItem['propio_id']) ? $dbItem['propio_id'] : "";

		$totalCount++;

		try {

			if(empty($propio_id)) {
				throw new \Exception("Empty propio_id");
			}

			$preparedData = array(
				'event_id' => "propio_requests",
				'config_id' => "propio_complete_request",
				'msg_type' => "",
				'template_id' => "",
				'message' => "",
				'to_send' => "",
				'operation_type' => 3,
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

			$updateData = array();

            $requestRes = PropioUtils::completeInterpreter($propio_id);


            if(is_array($requestRes) && !empty($requestRes)) {
                http_response_code(200);
                $updateData['request_responce'] = json_encode($requestRes);
            } else {
            	$updateData['request_responce'] = $requestRes;
            }

            print_r($preparedData);

			$eventLogId =  @ActionEvent::savePreparedData($preparedData);
			if(!empty($updateData)) @ActionEvent::updatePreparedData($eventLogId,$updateData);


			$totalCompletedRequestCount++;

		} catch(Exception $e) {
			echo 'Error: ' .$e->getMessage();
		}
	}

	// Close file
	fclose($cron_lock);
} catch(Exception $e) {
  	echo 'Error: ' .$e->getMessage();
}

echo "\n\nTotal Request Item: " . $totalCount . "\tTotal Completed Request Item: " . $totalCompletedRequestCount . "\n";