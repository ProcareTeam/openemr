<?php

$_SERVER['REQUEST_URI']=$_SERVER['PHP_SELF'];
$_SERVER['SERVER_NAME']='localhost';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SESSION['site'] = 'default';
$backpic = "";
$ignoreAuth=1;

require_once("../interface/globals.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\ZoomIntegration;

function getMeetingDetails() {
	$resultData = array();

	//$result = sqlStatement("SELECT * from zoom_appointment_events zae where date(created_at) <= '2023-08-07' and date(start_time) >= '2023-08-17' order by id desc");

	//$result = sqlStatement("SELECT * from zoom_appointment_events zae where date(start_time) >= '2023-08-18' order by start_time desc");

	//$result = sqlStatement("select ope.pc_eid, ope.pc_pid, ope.pc_eventDate, ope.pc_startTime, ope.pc_eventstatus, ope.pc_title, zae.m_id from openemr_postcalendar_events ope left join zoom_appointment_events zae on zae.pc_eid = ope.pc_eid where ope.pc_eid in (298990)");

	//$result = sqlStatement("select ope.pc_eid, ope.pc_pid, ope.pc_eventDate, ope.pc_time, ope.pc_title, zae.m_id, zae.start_time, zae.created_at from openemr_postcalendar_events ope left join zoom_appointment_events zae on ope.pc_eid = zae.pc_eid where ope.pc_catid in (54,53,41,34,29) and date(ope.pc_eventDate) >= '2023-08-19' order by date(ope.pc_eventDate) asc;");

	$result = sqlStatement("select ope.pc_eid,ope.pc_pid,ope.pc_eventDate,ope.pc_time,ope.pc_title,zae.m_id,zae.start_time,zae.created_at from openemr_postcalendar_events ope left join zoom_appointment_events zae on ope.pc_eid = zae.pc_eid where ope.pc_catid in (54, 53, 41, 34, 29) and date(ope.pc_eventDate) >= '2023-08-19' and m_id is null order by date(ope.pc_eventDate) asc");

	while ($row = sqlFetchArray($result)) {
		$resultData[] = $row;
	}

	return $resultData;
}

$res_return = true;
$meetingDetails = getMeetingDetails();
$count = 0;
$data = array();

foreach ($meetingDetails as $mItem) {
	if(isset($mItem['pc_eid'])) {
		$zRecreateRes = ZoomIntegration::recreateZoomMeeting($mItem['pc_eid'], '', true );
		//$zRecreateRes = ZoomIntegration::deleteZoomMeetingForAppt($mItem['m_id']);
		//$nmItem = $zRecreateRes;
		$nmItem = isset($zRecreateRes['data']) ? $zRecreateRes['data'] : array();

		$idata = array();
		foreach ($mItem as $item1) {
			$idata[] = $item1;
		}

		$idata[] = "";

		foreach ($nmItem as $item2) {
			$idata[] = $item2;
		}

		if(!empty($idata)) {
			$data[] = implode("|",$idata);
		}
	}

	$count++;
}

$fp = fopen('../log/meeting_data.csv', 'w');
foreach($data as $line){
	$val = explode("|",$line);
	fputcsv($fp, $val);
}
fclose($fp);

echo $count;
