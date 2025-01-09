<?php

include_once("../../../globals.php");
require_once($GLOBALS['srcdir'].'/calendar.inc');
require_once($GLOBALS['srcdir'].'/../interface/main/calendar/includes/pnAPI.php');
require_once($GLOBALS['srcdir'].'/../interface/main/calendar/php/calendar_fun.php');
require_once("$srcdir/wmt-v2/wmtstandard.inc");
require_once("$srcdir/wmt-v2/wmt.msg.inc");
require_once("$srcdir/OemrAD/oemrad.globals.php");
require_once($GLOBALS['srcdir']."/wmt-v2/wmtstandard.inc");
require_once($GLOBALS['srcdir']."/wmt-v2/wmt.msg.inc");
require_once($GLOBALS['srcdir']."/OemrAD/oemrad.globals.php");


use OpenEMR\OemrAd\Caselib;
use Vitalhealthcare\OpenEMR\Modules\Generic\Api\PatientFormController;
use OpenEMR\OemrAd\CoverageCheck;

//$pid = $_REQUEST['pid'] ? $_REQUEST['pid'] : '';
if(!isset($form_eid)) $form_eid = $_REQUEST['eid'] ? $_REQUEST['eid'] : '';
if(!isset($patient_info_status)) $patient_info_status = isset($_REQUEST['patient_info']) ? true : false;
if(!isset($c_info_status)) $c_info_status = isset($_REQUEST['coverage_info']) ? true : false;
if(!isset($pending_form_info_status)) $pending_form_info_status = isset($_REQUEST['pending_form_info']) ? true : false;
if(!isset($pending_order_info_status)) $pending_order_info_status = isset($_REQUEST['pending_order_info']) ? true : false;
$responce_text = "";

// OEMR - Get coverage content.
function getCoverageContent($event, $data) {
    $data = CoverageCheck::getEleContentForPostCalender($event, $data);
    return CoverageCheck::avabilityHTMLContent($data);
}

function getPendingFormList($pid) {
    $patientFormController =  new PatientFormController();
    $pendingForms = $patientFormController->getPatientPendingFormList($pid);

    return $pendingForms;
}

function getPendingOrderList($pid) {
	$pendingresult = sqlStatement("SELECT * from form_rto fr where pid = ? and rto_status = 'p' order by id desc;", array($pid));
	$resList = array();

    while ($row = sqlFetchArray($pendingresult)) {
        if(isset($row['id'])) {
        	$resList[] = $row['rto_date'] . " " . ListLook($row['rto_action'],'RTO_Action') . " - " . ListLook($row['rto_status'],'RTO_Status');
        }
    }

    return $resList;
}

$apptData = sqlQuery("SELECT ope.*, concat(pd.lname,', ',pd.fname) as patient_name, pd.DOB as patient_dob, pd.alert_info from openemr_postcalendar_events ope join patient_data pd on pd.pid = ope.pc_pid where ope.pc_eid = ? ", $form_eid);

if (!empty($apptData) && !empty($apptData['pc_eid']) && !empty($apptData['pc_pid'])) {
	$pid = isset($apptData['pc_pid']) ? $apptData['pc_pid'] : '';

	if ($patient_info_status === true) {
		$commapos = strpos(($apptData['patient_name'] ?? ''), ",");
	    $lname = substr(($apptData['patient_name'] ?? ''), 0, $commapos);
	    $fname = substr(($apptData['patient_name'] ?? ''), $commapos + 2);
	    $patient_dob = oeFormatShortDate($apptData['patient_dob']);
	    $patient_age = date("Y") - substr(($apptData['patient_dob']), 0, 4);
		$comment = $apptData['hometext'];
		
		$link_title = $fname . " " . $lname . " \n";
	    $link_title .= xl('Age') . ": " . $patient_age . "\n" . xl('DOB') . ": " . $patient_dob . " $comment" . "\n";
	    $link_title .= "(" . xl('Click to view') . ")";

	    if (!empty($apptData['alert_info'])) {
	    	$link_title .="\n\s\n-- Alert Info -- \n".$apptData['alert_info'];
		}

	    $responce_text .= $link_title;
	}

	if ($c_info_status === true) {
		$coverageData = CoverageCheck::getEligibilityDataForPostCalender($form_eid);
		$event = array(
			'eid' => isset($apptData['pc_eid']) ? $apptData['pc_eid'] : '',
			'pid' => $pid
		);
		// Get coverage content
		$coverage_info = getCoverageContent($event, $coverageData);
		if (!empty($coverage_info) && isset($coverage_info['title'])) {
			$responce_text .= $coverage_info['title'];
		}
	}

	// Pending Forms
	if ($pending_form_info_status === true) {
		// Pending Forms
		$pending_form_items = getPendingFormList($pid);

		if (!empty($pending_form_items)) {
			if (count($pending_form_items) > 0) {
				$responce_text .= "\n--Pending Forms--";
				foreach ($pending_form_items as $formitems) {
					$responce_text .= "\n" . $formitems['template_name'] . " - (" . strtoupper($formitems['status']) . ")";
				}

				$responce_text .= "\n";
			}
		}
	}

	if ($pending_order_info_status === true) {
		// Pending Orders
		$pending_order_items = getPendingOrderList($pid);
		if (!empty($pending_order_items) && count($pending_order_items) > 0) {
			$responce_text .= "\n--Pending Orders--";
			$i = 1;
			foreach ($pending_order_items as $formitems) {
				if ($i === 5) break;
				$responce_text .= "\n" . $formitems;
				$i++;
			}

			$trcount = count($pending_order_items) - 5;
			if ($trcount > 0) {
				$responce_text .= "\n" . (count($pending_order_items) - 5) . " more ...";
			}
		}
	}

}

$responce_text = str_replace('\s', '', $responce_text);
$responce_text = nl2br($responce_text);
$responce_text = preg_replace('/\s+/', ' ', $responce_text);
echo $responce_text;