<?php

include_once("../../../globals.php");
include_once("$srcdir/OemrAD/oemrad.globals.php");

use OpenEMR\OemrAd\Caselib;

$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : '';

function getCaseData($pid) {
	$dataSet = array();

	if(empty($pid)) {
		return $dataSet;
	}

	//$result = sqlStatement("SELECT pd.pubpid, fc.* FROM form_cases fc left join patient_data pd on pd.pid = fc.pid WHERE fc.pid  = ? and fc.closed = 0", $pid);
	$result = sqlStatement("SELECT pd.pubpid, fc.* FROM form_cases fc left join patient_data pd on pd.pid = fc.pid WHERE fc.pid  = ? order by fc.closed", $pid);
	while ($row = sqlFetchArray($result)) {
		$lp_results = sqlStatement("SELECT vpcmd.*, u.email FROM vh_pi_case_management_details vpcmd join users u on u.id = vpcmd.field_value WHERE vpcmd.case_id = ? AND vpcmd.field_name = 'lp_contact' ", array($row['id']));

		$lp_emails = array();
		while ($lprow = sqlFetchArray($lp_results)) {
			if(isset($lprow['field_value']) && !empty($lprow['field_value'])) {
				if(isset($lprow['email']) && !empty($lprow['email']) && !in_array($lprow['email'], $lp_emails)) {
					$lp_emails[] = $lprow['email'];
				}
			}
		}

		$row['email_list'] = "";
		if(!empty($lp_emails)) {
			$row['email_list'] = implode(",", $lp_emails);
		}
		
		$dataSet[] = $row;
	}

	return $dataSet;
}

$piTypeCases = array();
$cases = getCaseData($pid);

foreach ($cases as $ck => $case) {
	$liableData = Caselib::isLiablePiCaseByCase($case['id'], $pid, $case);

	if($liableData === true) {
		$piTypeCases[] = $case;
	}
}

echo json_encode($piTypeCases);
