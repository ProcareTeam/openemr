<?php

$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
$_SERVER['SERVER_NAME'] = 'localhost';
$_GET['site'] = 'default';
$backpic = "";
$ignoreAuth = 1;

$onsiteFormId = $argv[1] ?? "";

if (empty($onsiteFormId)) {
	echo "Wrong data";
	exit();
}

require_once(dirname(__FILE__, 8) . "/interface/globals.php");
require_once("$srcdir/patient.inc");

use OpenEMR\Common\Csrf\CsrfUtils;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;


$patientForm = new FormController(); 

$onsiteFormResult = sqlQuery("SELECT vof.* FROM `vh_onsite_forms` vof where vof.`id` = ? ", array($onsiteFormId));


if (empty($onsiteFormResult)) {
	echo "No data found";
	exit();
}

$formDataId = $onsiteFormResult['ref_id'] ?? "";
$formPid = $onsiteFormResult['pid'] ?? "";

if (empty($formDataId) || empty($formPid)) {
	echo "Empty data";
	exit();
}

$formAssocResult = $patientForm->getFormAssocItems($formDataId);

foreach ($formAssocResult as $formassocrow) {
	if(isset($formassocrow['id'])) {
		$formDetails = $patientForm->getUserFormData($formassocrow['id'], $formPid);
		$formDetails = count($formDetails) > 0 ? $formDetails[0] : array();

		$formLogDataDetails = isset($formDetails['form_data']) ? $formDetails['form_data'] : array();

		$formTemplateDetails = isset($formDetails['template']) ? $formDetails['template'] : array();


		$docLogItems = $patientForm->getFormDocumentsLog($formassocrow['id']);
		$regeneratePDF = false;

		if(empty($docLog)) {
			$regeneratePDF = true;
		} else if(isset($formLogDataDetails['received_date']) && !empty($formLogDataDetails['received_date']) && !empty($docLog)) {
			$dlItem = reset($docLog);

			if(isset($dlItem['created_date']) && !empty($dlItem['created_date'])) {
				if(strtotime($dlItem['created_date']) < strtotime($formLogDataDetails['received_date'])) {
					$regeneratePDF = true;
				}
			}
		}

		$isEnableToReview = isset($formDetails['status']) && in_array($formDetails['status'], array(FormController::SUBMIT_LABEL)) ? true : false;

		if($isEnableToReview === true && $regeneratePDF === true && !empty($formTemplateDetails)) {
			$formDocData =  $patientForm->getFormDocumentsLog($formassocrow['id']);
		    $tName = isset($formTemplateDetails['template_name']) ? $formTemplateDetails['template_name'] : "";

		    if(!empty($tName)) {
		    	$patientResult = getPatientData( $formDetails['form_data']['pid'], "pubpid");

				$patientForm->saveFormPDF($formassocrow['id'], $formPid, $tName, array("submitted_on" => $formDetails['form_data']['received_date'], "pubpid" => $patientResult['pubpid'])); 
			}
		}
	}
}

exit();