<?php

require_once("../../../globals.php");
require_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\Caselib;

$pid = $_REQUEST['pid'] ? $_REQUEST['pid'] : '';
$caseId = $_REQUEST['caseId'] ? $_REQUEST['caseId'] : '';
$totalCount = $_REQUEST['totalCount'] ? $_REQUEST['totalCount'] : '';
$futureAppt = $_REQUEST['futureAppt'] ? $_REQUEST['futureAppt'] : '';
$rehabProgress = $_REQUEST['rehabProgress'] ? $_REQUEST['rehabProgress'] : '';


$response = array(
	'status' => false,
	'total_count1' => 0,
	'total_count2' => 0
);

function getCancelledAppt($pid, $from = '', $to = '') {
	$totalCount1 = 0;
	$totalCount2 = 0;

	if($pid) {
		$results = array();

		$ope_sql = "SELECT count(ope.pc_eid) as total_appt from `openemr_postcalendar_events` ope WHERE pc_apptstatus in ('%', '?') and pc_pid = ".$pid." and pc_eventDate between '".$from."' and '".$to."' order by pc_eventDate desc";		
		$result1 = sqlQuery($ope_sql);
		if(isset($result1) && isset($result1['total_appt'])) {
			$totalCount1 = $result1['total_appt'];
		}

		$ope_sql1 = "SELECT count(ope.pc_eid) as total_appt from `openemr_postcalendar_events` ope left join users u on u.id = ope.pc_aid WHERE pc_apptstatus in ('%', '?') and pc_pid = ".$pid." and pc_eventDate between '".$from."' and '".$to."' and u.taxonomy in ('111N00000X', '207Q00000X') order by pc_eventDate desc";		
		$result2 = sqlQuery($ope_sql1);
		if(isset($result2) && isset($result2['total_appt'])) {
			$totalCount2 = $result2['total_appt'];
		}
	}

	$totalCount1 = $totalCount1 - $totalCount2;

	return array(
		'total_count1' => $totalCount1,
		'total_count2' => $totalCount2
	);
}

function getFutureAppt($pid, $date = '') {
	$resultItems = array();

	$whereSql = "ope.pc_pid = ? ";
	$bind = array($pid);

	if(!empty($date)) {
		$whereSql .= "and TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) > ? ";
		$bind = array($date);
	} else {
		$whereSql .= "and TIMESTAMP(ope.pc_eventDate, ope.pc_startTime) > now() ";
	}

	$sql = "SELECT ope.*, opc.pc_catname, u.fname as provider_fname, u.mname as provider_mname, u.lname as provider_lname, TIMESTAMP(pc_eventDate, pc_startTime) as event_date_time from openemr_postcalendar_events ope left join users u on u.id is not null and u.id = ope.pc_aid left join openemr_postcalendar_categories opc on opc.pc_catid = ope.pc_catid where ".$whereSql." order by event_date_time asc ;";
	$result = sqlStatementNoLog($sql, $bind);
	while ($result_data = sqlFetchArray($result)) {
		$result_data['provider_name'] = ucfirst(substr($result_data['provider_fname'], 0, 1)) . $result_data['provider_lname'];

		$provider_name = isset($result_data['provider_name']) ? $result_data['provider_name'] : "";
		$event_date = isset($result_data['event_date_time']) ? date('m/d/Y',strtotime($result_data['event_date_time'])) : "";
		$pc_time = isset($result_data['event_date_time']) ? date('h:iA',strtotime($result_data['event_date_time'])) : "";
		$pc_catname = isset($result_data['pc_catname']) ? $result_data['pc_catname'] : "";

		$resultItems[] = $event_date . " - " . $provider_name . " - " . $pc_catname . " - " . $pc_time;
	}

	return $resultItems;
}

if(isset($pid) && !empty($pid)) {
	if(isset($totalCount) && $totalCount == "1") {
		$toDate = date('Y-m-d', strtotime("-1 days"));
		$fromDate = date('Y-m-d', strtotime("-6 months", strtotime($toDate)));

		//Total Count
		$totalCount = getCancelledAppt($pid, $fromDate, $toDate);
		$response['total_count1'] = $totalCount['total_count1'];
		$response['total_count2'] = $totalCount['total_count2'];
	}

	if(isset($futureAppt) && $futureAppt == "1") {
		$response['future_appt_list'] = getFutureAppt($pid);
	}

	if(isset($rehabProgress) && $rehabProgress == "1" && !empty($caseId)) {
		//Get LBF Data
		$caseManagerData = Caselib::piCaseManagerFormData($caseId, '');
		$lbfFormDataItems = Caselib::getRehabProgressLBFData(array($caseId));
		$lbfFormData = isset($lbfFormDataItems['case_' . $caseId]) ? $lbfFormDataItems['case_' . $caseId] : array();

		if(!empty($lbfFormData)) {
			$finalDataSet = array(
				"PT" => isset($lbfFormData['pt']) ? $lbfFormData['pt'] : 0,
				"LD" => isset($lbfFormData['ld']) ? $lbfFormData['ld'] : 0,
				"CD" => isset($lbfFormData['cd']) ? $lbfFormData['cd'] : 0,
				"DD" => isset($lbfFormData['dd']) ? $lbfFormData['dd'] : 0
			);
		}

		$isPiCaseLiable = Caselib::isLiablePiCaseByCase($caseId, $pid);
		
		if($isPiCaseLiable === true) {
			$rehabPlanData = Caselib::getRehabPlanDataByCase($caseId, $caseManagerData);
			$rehabPlanItems = array();

			foreach ($rehabPlanData as $rpd => $rpdItem) {
				$apptCount = isset($finalDataSet[$rpdItem['id']]) ? $finalDataSet[$rpdItem['id']] : 0;

				$rehabPlanItems[] = $rpdItem['id'] . " " . $apptCount . "/" . $rpdItem['value_sum'];
			}

			if(!empty($rehabPlanItems)) {
				$rehabPlanItems = implode(", ", $rehabPlanItems);
			} else {
				$rehabPlanItems = "";
			}
		}

		$response['rehab_progress'] = isset($rehabPlanItems) ? $rehabPlanItems : "";
	}
}

echo json_encode($response);
