<?php

require_once(dirname(__FILE__, 8) . "/interface/globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;

$formId = isset($_REQUEST['form_id']) ? $_REQUEST['form_id'] : "";
$formType = "form";

$p1 = new FormController();
$ftv = $p1->getFormIdType($formId);

if(isset($ftv['formId']) && $ftv['formType']) {
	$formId = $ftv['formId'];
	$formType = $ftv['formType'];
}

$isOnetime = true;

if($_REQUEST['form_action'] == "patient_template") {
	$formPid = isset($_REQUEST['form_pid']) ? $_REQUEST['form_pid'] : "";

	$patientForm = new FormController();
	$patientFormTemplate = $patientForm->getFormTemplateList(array(
		"pid" => $formPid
	));

	$patientPacketTemplate = $patientForm->getPacketTemplateList(array(
		"pid" => $formPid
	));

	if(empty($patientFormTemplate) && empty($patientPacketTemplate)) {
		echo "<option value=''>Please Select</option>";
		exit();
	}

	$resultItems = array();
	$strOut = "<option value=''>Please Select</option>";
	foreach ($patientFormTemplate as $ptItem) {
		$resultItems['f' . $ptItem['id']] = $ptItem;
		$strOut .= "<option value='f" . $ptItem['id'] . "'>" . $ptItem['template_name'] . " (".strtoupper(FormController::FORM_LABEL).") </option>";
	}

	foreach ($patientPacketTemplate as $ptItem) {
		$resultItems['p' . $ptItem['id']] = $ptItem;
		$strOut .= "<option value='p" . $ptItem['id'] . "'>" . $ptItem['template_name'] . " (".strtoupper(FormController::PACKET_LABEL).") </option>";
	}
 
	echo json_encode(array(
		'list' => $resultItems,
		'content' => $strOut
	));
} else if ($_REQUEST['form_action'] == "patient_form_data" && !empty($formId)) {
	$formPid = isset($_REQUEST['form_pid']) ? $_REQUEST['form_pid'] : "";
	$pageAction = isset($_REQUEST['page_action']) ? $_REQUEST['page_action'] : "";

	$formStatus = false;
	if($pageAction === "resend_after_submit") {
		$formStatus = true;
	}

	$patientForm = new FormController();
	$patientFormData = $patientForm->getFormTemplateDataList($formId, $formPid, $formType, $formStatus);

	if(!empty($patientFormData['list']) && isset($patientFormData["hasAccess"]) && $patientFormData["hasAccess"] === false) {
		echo "<option value=''>Please Select</option>";
		exit();
	}
	
	$strOut = "<option value='new'>New Form</option>";
	$alreadyInUse = array();

	foreach ($patientFormData['list'] as  $pfItem) {
		$formTemplate = isset($pfItem['template']) ? $pfItem['template'] : array();

		if(!in_array($formTemplate["id"], $alreadyInUse)) {
			$alreadyInUse[] = $formTemplate["id"];
			$strOut .= "<optgroup label='" . $formTemplate['template_name'] . "'>";

			foreach ($patientFormData["list"] as $pfItem1) {

				$formStatus = is_array($pfItem1['status']) ? end($pfItem1['status']) : $pfItem1['status'];
				
				$formTemplate1 = isset($pfItem1['template']) ? $pfItem1['template'] : array();
				$forms = isset($pfItem1['template']) ? $pfItem1['template'] : array();

				if($formTemplate1["id"] == $formTemplate["id"]) {
					$strOut .= "<option value='". $pfItem1['form_data_id'] ."'>". $pfItem1['created_date'] ." (". strtoupper($formStatus) .")</option>";
				}
			}

			$strOut .= "</optgroup>";
		}
	}

	// 
	$countResult = sqlStatementNoLog("SELECT vof.status from vh_onsite_forms vof join vh_form_data_log vfdl on vfdl.id = vof.ref_id join vh_onetimetoken_form_log vofl on vofl.ref_id = vof.ref_id join onetime_auth oa on vofl.onetime_token_id = oa.id where vfdl.form_id = ? and vfdl.`type` = ? and vof.pid = ? and ((vof.status in ('".FormController::SAVE_LABEL."', '".FormController::PENDING_LABEL."') and FROM_UNIXTIME(oa.expires) > NOW()) OR vof.status in ('".FormController::SUBMIT_LABEL."')) GROUP BY (vof.status)", array($formId, $formType, $formPid));

    $dataItemStatusList = [];
    while ($statusListrow = sqlFetchArray($countResult)) {
    	if(!empty($statusListrow['status'])) {
    		$dataItemStatusList[] = $statusListrow['status'];
    	}
    }

	echo json_encode(array(
		'status' => $dataItemStatusList,
		'content' => $strOut
	));
	exit();
} else if ($_REQUEST['form_action'] == "send_token" && !empty($formId)) {
	try {
		$formDataId = isset($_REQUEST['form_data_items']) ? $_REQUEST['form_data_items'] : "";
		$formPid = isset($_REQUEST['form_pid']) ? $_REQUEST['form_pid'] : "";
		$formNotificationMethod = isset($_REQUEST['notification_method']) ? $_REQUEST['notification_method'] : "";
		$formEmailTemplate = isset($_REQUEST['formEmailTemplate']) ? $_REQUEST['formEmailTemplate'] : "";
		$formSMSTemplate = isset($_REQUEST['formSMSTemplate']) ? $_REQUEST['formSMSTemplate'] : "";
		$pageAction = isset($_REQUEST['page_action']) ? $_REQUEST['page_action'] : "";

		$formFieldData = isset($_REQUEST['form_field_data']) && !empty($_REQUEST['form_field_data']) ? json_decode($_REQUEST['form_field_data'], true) : array();

		$patientForm = new FormController();

		$reqBeforeSendingFieldList = $patientForm->checkRequiredFieldBeforeSend($formId, $formDataId, $formType, $formFieldData);
		$reqBeforeSendingFieldStatus = false;

		if(!empty($reqBeforeSendingFieldList) && $reqBeforeSendingFieldList !== false) {
			$reqBeforeSendingFieldStatus = true;
			throw new \Exception("Required field before sending. \n\n" . implode("\n", $reqBeforeSendingFieldList['req_fields']) . "");
		}

		if($pageAction === "resend_after_submit") {

			$assocFormData = $patientForm->getOnsiteDataItems(array('data_id' => $formDataId, "other_details" => false));
            $assocFormData = !empty($assocFormData) ? reset($assocFormData) : array();
            $assocFormItems = isset($assocFormData['form_items']) ? $assocFormData['form_items'] : array();

            foreach ($assocFormItems as $frow) {
                if(isset($frow['id']) && !empty($frow['id'])) {

                	// Update Onasite
                    $sqlRes = $patientForm->updateOnsiteForms(array(
                        "status" => FormController::SAVE_LABEL
                    ), $frow['id']);
                }
            }
		}

		$tokenData = $patientForm->sendFormToken($formId, $formDataId, $formType, $formPid, $formNotificationMethod, $formEmailTemplate, $formSMSTemplate, true);

		if($formDataId == "new" && !empty($formFieldData) && isset($tokenData['form_data_id'])) {
			
			// GEt onsite data items
			$onsiteData = $patientForm->getOnsiteDataItems(array(
				"data_id" => $tokenData['form_data_id']
			));

			if(!empty($onsiteData)) {
				foreach ($onsiteData as $onsiteItem) {

					if(isset($onsiteItem['form_items'])) {
						foreach ($onsiteItem['form_items'] as $onsiteformItem) {

							if(isset($onsiteformItem['form_template_id']) && isset($formFieldData[$onsiteformItem['form_template_id']])) {

								// $sqlRes = $patientForm->updateOnsiteForms(array(
			                    //     "template_data" => json_encode($formFieldData[$onsiteformItem['form_template_id']])
			                    // ), $onsiteformItem['id']);

								$bodyJSONParams = isset($formFieldData[$onsiteformItem['form_template_id']]) ? $formFieldData[$onsiteformItem['form_template_id']] : array();
			                    $bodyData = isset($bodyJSONParams['data']) ? json_decode(json_encode($bodyJSONParams['data']), true) : array();

								$saveResult = $patientForm->savePatientForm($bodyData, 'fill', $onsiteformItem['id'], $formPid, '');

				                if(empty($saveResult)) {
				                    throw new \Exception("Empty Result");
				                }

							}
						}
					}
				}
			}
		}
		
		echo json_encode(array('status' => true, "data" => $tokenData));

	} catch (\Throwable $e) {
		echo json_encode(array('status' => false, "errors" => $e->getMessage(), "reqBeforeSendingFieldStatus" => isset($reqBeforeSendingFieldStatus) ? $reqBeforeSendingFieldStatus : ""));
    }

	exit();
}