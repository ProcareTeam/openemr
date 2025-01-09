<?php

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Controller;

require_once($GLOBALS['fileroot'] . "/library/documents.php");
require_once($GLOBALS['fileroot'] . "/library/classes/Document.class.php");
require_once($GLOBALS['fileroot'] . "/library/OemrAD/classes/mdReminder.class.php");
require_once($GLOBALS['fileroot'] . "/library/OemrAD/classes/mdActionEvent.class.php");
require_once($GLOBALS['fileroot'] . "/library/OemrAD/classes/mdEmailMessage.class.php");
require_once($GLOBALS['fileroot'] . "/library/OemrAD/classes/mdMessagesLib.class.php");
require_once($GLOBALS['fileroot'] . "/library/OemrAD/classes/mdShortenLink.class.php");
require_once($GLOBALS['fileroot'] . "/library/forms.inc");
require_once($GLOBALS['srcdir']."/wmt-v3/wmt.globals.php");

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\OemrAd\Reminder;
use OpenEMR\OemrAd\EmailMessage;
use OpenEMR\OemrAd\MessagesLib;
use OpenEMR\OemrAd\ActionEvent;
use OpenEMR\OemrAd\ShortenLink;
use OpenEMR\Common\Auth\OneTimeAuth;
use OpenEMR\Services\PatientService;
use Mpdf\Mpdf;
use RestConfig;
use DateInterval;
use DateTime;

class FormController
{

	private $tokenExpireTime;
    private $defaultDocumentCategory;

    const PENDING_LABEL = "pending";
    const SAVE_LABEL = "saved";
    const SUBMIT_LABEL = "submited";
    const REVIEW_LABEL = "reviewed";
    const PREVIEW_LABEL = "preview";
    const REJECT_LABEL = "rejected";
    const EXPIRE_LABEL = "expired";
    const FORM_LABEL = "form";
    const PACKET_LABEL = "packet";

	public function __construct()
    {
        $this->tokenExpireTime = $GLOBALS['fm_form_token_expiretime'];
        $this->defaultDocumentCategory = $GLOBALS['fm_default_document_category'];
        $this->formPortalUrl = $GLOBALS['fm_form_portal_url'];
        $this->formPortalFormUrl = $this->formPortalUrl . '/form/';
    }

    public function verifyToken($token = "") {
        try {

            if(empty($token)) {
                $headers = apache_request_headers();
                $token = isset($headers["x-token"]) ? $headers["x-token"] : "";
            }

            if(empty($token)) {
                throw new \Exception("Unauthorized");
            }

            $oneTime = new OneTimeAuth();
            $auth = $oneTime->decodePortalOneTime($token, null);
            if (!empty($auth['error'] ?? null)) {
                unset($auth);
                throw new \Exception("Authentication Failed! Contact administrator.");
            }
            $patientService = new PatientService();
            $patientData = $patientService->findByPid($auth['pid']);

            $logData = sqlQueryNoLog("SELECT * FROM `vh_onetimetoken_form_log` WHERE `onetime_token` = ? ", array($token));

            if(empty($logData) || empty($logData['ref_id'])) {
                throw new \Exception("Unauthorized");
            }

            if(empty($logData['ref_id'])) {
                throw new \Exception("Unauthorized");
            }

            if(empty($patientData) && !is_array($patientData) && count($patientData)) {
                throw new \Exception("Unauthorized");
            }

            $formResult = sqlStatementNoLog("SELECT vof.* FROM `vh_onsite_forms` vof where vof.`ref_id` = ? ", array($logData['ref_id']));

            $formIdList = array();
            while ($frow = sqlFetchArray($formResult)) {
                if(isset($frow['id']) && !empty($frow['id'])) {
                    $formIdList[] = $frow['id'];
                }
            }

            unset($patientData['uuid']);
            $auth['patient'] = $patientData;
            $auth['refId'] = $logData['ref_id'];
            $auth['formId'] = $formIdList;
            $auth['tokenId'] = isset($logData['onetime_token_id']) ? $logData['onetime_token_id'] : "";

            return $auth;
        } catch (\Throwable $e) {
            throw new \Exception("Unauthorized");
        }

        throw new \Exception("Unauthorized");
    }

    // Get Form Template Details
	public function getFormTemplates($formId = "", $status = "", $selectQtr = "vft.*") {
		$strWhere = "";
		$binds = array();

		if(!empty($formId)) {
			$strWhere .= " and vft.id = ? ";
			$binds[] = $formId;
		}

		if(!empty($status)) {
			$strWhere .= " and vft.status = ? ";
			$binds[] = $status;
		}

		$ftResult = sqlStatementNoLog("SELECT " . $selectQtr . " from vh_form_templates vft where vft.id != '' " . $strWhere . " order by vft.id desc", $binds);

		$ftReturn = [];
		while ($ftRow = sqlFetchArray($ftResult)) {
			$ftReturn[] = $ftRow;
		}

		return $ftReturn;
	}

    // Get Form Template Details
    public function getFormTemplatesByIds($formId = array(), $status = "", $selectQtr = "vft.*") {
        $strWhere = "";
        $binds = array();
        if(!empty($formId) && is_array($formId)) {
            $strWhere .= " and vft.id in ('" . implode("','", $formId) .  "') ";
        }
        if(!empty($status)) {
            $strWhere .= " and vft.status = ? ";
            $binds[] = $status;
        }
        $ftResult = sqlStatementNoLog("SELECT " . $selectQtr . " from vh_form_templates vft where vft.id != '' " . $strWhere . " order by vft.id desc", $binds);
        $ftReturn = [];
        while ($ftRow = sqlFetchArray($ftResult)) {
            $ftReturn[] = $ftRow;
        }
        return $ftReturn;
    }

    // Get Onsite Form Data By Id
	public function getOnsiteForms($formDataId = "", $authPid = "") {
		$strWhere = [];
		$binds = array();

		if(!empty($formDataId)) {
			$strWhere[] = "vof.id = ?";
			$binds[] = $formDataId;
		}

		if(!empty($authPid)) {
			$strWhere[] = "vof.pid = ?";
			$binds[] = $authPid;
		}

		if(!empty($strWhere)) {
            $strWhere = " where " . implode(" and ", $strWhere);
        } else {
            $strWhere = "";
        }

		$fResult = sqlStatementNoLog("SELECT vfdl.id as `data_id`, vof.*, CONCAT(LEFT(us.`fname`,1), '. ',us.`lname`) AS 'reviewed' from `vh_onsite_forms` vof JOIN `vh_form_data_log` vfdl on vfdl.id = vof.ref_id  LEFT JOIN `users` us ON vof.`reviewer` = us.`id` " . $strWhere . " order by vof.id desc", $binds);

		$fReturn = [];
		while ($frow = sqlFetchArray($fResult)) {
			$fReturn[] = $frow;
		}

		return $fReturn;
	}

	public function updateOnsiteForms($data, $formDataId = "") {
		$setPart = array();
        $binds = array();

        if(empty($formDataId)) {
        	return false;
        }

        foreach ($data as $key => $value) {
        	if($value == "CURRENT_TIMESTAMP") {
        		$setPart[] = $key . " = CURRENT_TIMESTAMP ";
        		continue;
        	}

        	$setPart[] = $key . " = ?";
        	$binds[] = $value;
        }

        $binds[] = $formDataId;

        return sqlQueryNoLog("UPDATE vh_onsite_forms SET ".implode(', ', $setPart)." WHERE id = ? ", $binds, true);
	}

	public function deleteOnsiteForms($formDataId) {
		if(empty($formDataId)) {
			return false;
		}

        $dataItems = $this->getOnsiteDataItems(array('data_id' => $formDataId));

        foreach ($dataItems as $dItem) {
            $tokenData = isset($dItem['token']) ? $dItem['token'] : array();
            
            sqlQueryNoLog("DELETE FROM `vh_form_data_log` WHERE `id` = ? ", array($dItem['form_data_id']));

            sqlQueryNoLog("DELETE FROM `vh_onsite_forms` WHERE `ref_id` = ?", array($dItem['form_data_id']));

            if(!empty($tokenData)) {
                $logData = sqlQueryNoLog("SELECT * FROM `vh_onetimetoken_form_log` WHERE `id` = ? ", array($tokenData['token_id']));

                if(!empty($logData)) {
                    $this->deleteOnetimetokenFormLog($tokenData['token_id']);
                    sqlQueryNoLog("DELETE FROM `onetime_auth` WHERE `id` = ?", array($logData['onetime_token_id']));
                }
            }
        }
	}

    public function setDeleteStatusOnsiteForms($formDataId) {
        if(empty($formDataId)) {
            return false;
        }

        if(!empty($formDataId)) {
            sqlQueryNoLog("UPDATE `vh_onsite_forms` SET `deleted` = 1 WHERE id = ?", array($formDataId));
        }
        
        $formData = $this->getOnsiteForms($formDataId);
        $formData = !empty($formData) && count($formData) === 1 ? $formData[0] : array();

        if(!empty($formData)) {
            $templateSchema = isset($formData['full_document']) ? json_decode($formData['full_document'], true) : array();
            $templateData = isset($formData['template_data']) ? json_decode($formData['template_data'], true) : array();

            $templateFieldList = array();
            $fieldTree = $this->printAllValues($templateSchema['components']);
            foreach ($fieldTree as $cItem) {
                if(isset($cItem['key'])) {
                    $templateFieldList[] = $cItem['key'];
                }
            }

            foreach ($templateData['data'] as $fieldKey => $fieldItems) {
                if(isset($fieldTree[$fieldKey]) && isset($fieldTree[$fieldKey]['type']) && $fieldTree[$fieldKey]['type'] == "file" ) {

                    foreach ($fieldItems as $fKey => $fItem) {
                        if(isset($fItem['document']) && isset($fItem['document']['id'])) {
                            $document_id = $fItem['document']['id'];

                            if(!empty($document_id)) {
                                $this->delete_document($document_id, false);
                            }
                        }
                    }
                }
            }
        }


        // Delete Review PDF
        $formDocLog = $this->getFormDocumentsLog($formDataId);
        $formDocLog = !empty($formDocLog) && count($formDocLog) === 1 ? $formDocLog[0] : array();
        
        if(!empty($formDocLog) && isset($formDocLog['doc_id']) && !empty($formDocLog['doc_id'])) {
            $this->delete_document($formDocLog['doc_id'], false);
        }

    }

	public function deleteOnetimetokenFormLog($log_id) {
		if(empty($log_id)) {
			return false;
		}

		return sqlQueryNoLog("DELETE FROM `vh_onetimetoken_form_log` WHERE id = ?", array($log_id));
	}

    public function getFormDocumentsLog($formId = "") {
        $strWhere = [];
        $binds = array();

        if(!empty($formId)) {
            $strWhere[] = "vfdl.form_id = ?";
            $binds[] = $formId;
        }

        if(!empty($strWhere)) $strWhere = " where " . implode(" and ", $strWhere);

        $fResult = sqlStatementNoLog("SELECT vfdl.* from vh_form_documents_log vfdl " . $strWhere . " order by vfdl.created_date desc", $binds);

        $fReturn = [];
        while ($frow = sqlFetchArray($fResult)) {
            $fReturn[] = $frow;
        }

        return $fReturn;
    }

	public function getUserFormData($itemId = "", $authPid = "") {
		$osForms = $this->getOnsiteForms($itemId, $authPid);

		$returnItems = [];
		foreach ($osForms as $fItem) {
			if(isset($fItem['form_id'])) {
				$fTemplates = $this->getFormTemplates($fItem['form_id']);

				if(is_array($fTemplates) && !empty($fTemplates) && count($fTemplates) === 1) {
					$returnItems[] = array(
						'template' => $fTemplates[0],
						'form_data' => $fItem,
						'full_document' => isset($fItem['full_document']) ? $fItem['full_document'] : "",
						'template_data' => isset($fItem['template_data']) ? $fItem['template_data'] : "",
						'status' => isset($fItem['status']) ? $fItem['status'] : ""
					);
				}
			}
		}

		return $returnItems;
	}

	public function getAssignedUserFormData($itemId = "", $authPid = "") {
		$formDataItems = $this->getUserFormData($itemId, $authPid);

		$returnItems = [];
		foreach ($formDataItems as $fItem) {
			$formTemplate = isset($fItem['template']) ? $fItem['template'] : array();
			$isFormAssigned = $this->isFormAssigned($formTemplate, $authPid);

			if($isFormAssigned === true) {
				$returnItems[] = $fItem;
			}
		}

		return is_array($returnItems) && count($returnItems) === 1 ? $returnItems[0] : $returnItems;
	}

    public function is_assoc($associateArray) {
        // Keys of the array
        $keys = array_keys($associateArray);

        // If the array keys of the keys match the keys, then the array must
        // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
        return array_keys($keys) !== $keys;
    }

	public function isFormAssigned($formTemplate = array(), $authPid = "") {
        $isAssociated = $this->is_assoc($formTemplate);

        if($isAssociated === true) {
            $toAssigned = isset($formTemplate) && isset($formTemplate['to_patient']) && !empty($formTemplate['to_patient']) ? explode("|", $formTemplate['to_patient']) : array();

            if(in_array($authPid, $toAssigned) || in_array(-1, $toAssigned)) {    
                return true;
            }
        } else if($isAssociated === false) {
            $assignStatus = true;
            foreach ($formTemplate as $fTemplate) {
                $toAssigned = isset($fTemplate) && isset($fTemplate['to_patient']) && !empty($fTemplate['to_patient']) ? explode("|", $fTemplate['to_patient']) : array();
                if(in_array($authPid, $toAssigned) || in_array(-1, $toAssigned)) {    
                } else {
                    $assignStatus = false;
                }
            }

            return $assignStatus;
        }

        return false;
    }

    // Get Form Template List
    public function getFormTemplateList($opts = array()) {
        $formId = isset($opts['form_id']) ? $opts['form_id'] : "";
        $authPid = isset($opts['pid']) ? $opts['pid'] : "";

        $formTemplateList = $this->getFormTemplates($formId, "1", "vft.id, vft.template_name, vft.email_template, vft.sms_template, vft.status, vft.to_patient, vft.uid");

        $formTemplateResults = [];
        foreach ($formTemplateList as $formTemplateItem) {
            $isFormAssigned = $this->isFormAssigned($formTemplateItem, $authPid);

            if($isFormAssigned === true) {
                $formTemplateResults[] = $formTemplateItem;
            }
        }

        return $formTemplateResults;
    }

    public function getOnsiteDataItems($opts = array(), $orderby = "") {
        $dataId = isset($opts['data_id']) ? $opts['data_id'] : "";
        $formId = isset($opts['form_id']) ? $opts['form_id'] : "";
        $authPid = isset($opts['pid']) ? $opts['pid'] : "";
        $datatype = isset($opts['type']) ? $opts['type'] : "";
        $itemStatus = isset($opts['status']) ? $opts['status'] : "";
        $otherDetails = isset($opts['other_details']) ? $opts['other_details'] : true;
        $whereStr = array();
        $binds = array();

        if(!empty($dataId)) {
            $whereStr[] = "vfdl.id = ?";
            $binds[] = $dataId;
        }

        if(!empty($formId)) {
            $whereStr[] = "vfdl.form_id = ?";
            $binds[] = $formId;
        }

        if(!empty($datatype)) {
            $whereStr[] = "vfdl.type = ?";
            $binds[] = $datatype;
        }

        if(!empty($itemStatus)) {
            $whereStr[] = "vof.status IN ('" . implode("','", $itemStatus) . "')";
        }

        if(!empty($authPid)) {
            $whereStr[] = "vof.pid = ?";
            $binds[] = $authPid;
        }

        $whereStr = implode(" AND ", $whereStr);
        if(!empty($whereStr)) {
            $whereStr = " WHERE " . $whereStr;
        }

        $results = sqlStatementNoLog("SELECT vof.*, vfdl.`id` as `form_data_id`, vfdl.`type` as `form_type`, vfdl.form_id as `form_id`, vof.form_id as form_template_id, vfdl.created_date as `data_created_date`, vofl.`onetime_token`, vofl.`onetime_token_id`, vofl.`id` as `token_id` from vh_onsite_forms vof join vh_form_data_log vfdl on vof.ref_id = vfdl.id left join vh_onetimetoken_form_log vofl on vofl.ref_id = vfdl.id " . $whereStr . " " . $orderby , $binds);

        $finalResult = array();
        while ($row = sqlFetchArray($results)) {
            if(!isset($finalResult['i' . $row['form_data_id']])) {
                $finalResult['i' . $row['form_data_id']] = array(
                    'form_data_id' => $row['form_data_id'],
                    'form_id' => $row['form_id'],
                    'form_template_id' => $row['form_template_id'],
                    'form_type' => $row['form_type'],
                    'created_date' => "",
                    'status' => "",
                    'form_items' => array(),
                    'template' => array(),
                    'token' => array(
                        'onetime_token' => $row['onetime_token'],
                        'onetime_token_id' => $row['onetime_token_id'],
                        'token_id' => $row['token_id']
                    )
                );

                if($otherDetails === true) {
                    if($row['form_type'] == FormController::FORM_LABEL) {
                        $ft = $this->getFormTemplates($row['form_id']);

                        unset($ft[0]['template_content']);

                        if(!empty($ft)) {
                          $finalResult['i' . $row['form_data_id']]['created_date'] = $row['created_date'];
                          $finalResult['i' . $row['form_data_id']]['status'] = $row['status'];
                          $finalResult['i' . $row['form_data_id']]['template'] = $ft[0];
                        }
                    } else if($row['form_type'] == FormController::PACKET_LABEL) {
                        $packetTemplateList = $this->getPacketTemplates($row['form_id']);
                        
                        if(!empty($packetTemplateList) && count($packetTemplateList)) {
                            $finalResult['i' . $row['form_data_id']]['created_date'] = $row['data_created_date'];

                            $finalResult['i' . $row['form_data_id']]['status'] = array(FormController::PENDING_LABEL);
                            $finalResult['i' . $row['form_data_id']]['template'] = $packetTemplateList[0];
                        }
                    }
                } else {
                    // Else set status value
                    if($row['form_type'] == FormController::FORM_LABEL) {
                        $finalResult['i' . $row['form_data_id']]['status'] = $row['status'];
                    } else if($row['form_type'] == FormController::PACKET_LABEL) {
                        $finalResult['i' . $row['form_data_id']]['status'] = array(FormController::PENDING_LABEL);
                    }

                    $finalResult['i' . $row['form_data_id']]['created_date'] = $row['data_created_date'];
                }
            }

            // For Packet status
            if(is_array($finalResult['i' . $row['form_data_id']]['status'])) {
                if($row['status'] == FormController::PENDING_LABEL) {
                    $finalResult['i' . $row['form_data_id']]['status'][0] = FormController::PENDING_LABEL;
                } else if($row['status'] == FormController::SAVE_LABEL) {
                    $finalResult['i' . $row['form_data_id']]['status'][1] = FormController::SAVE_LABEL;
                } else if($row['status'] == FormController::REVIEW_LABEL) {
                    $finalResult['i' . $row['form_data_id']]['status'][2] = FormController::REVIEW_LABEL;
                } else if($row['status'] == FormController::REJECT_LABEL) {
                    $finalResult['i' . $row['form_data_id']]['status'][3] = FormController::REJECT_LABEL;
                }
            }

            $finalResult['i' . $row['form_data_id']]['form_items'][] = $row;
        }

        return $finalResult;
    }

    // Get Form Template Data List
	public function getFormTemplateDataList($templateId, $authPid, $formType = "form", $allStatus = false) {
        $formTemplateList = $this->getFormTemplates($templateId, "1");

        $formStatusList = array(FormController::SAVE_LABEL, FormController::PENDING_LABEL);
        if($allStatus === true) {
            $formStatusList[] = FormController::SUBMIT_LABEL;
        }

        $formDataItems = $this->getOnsiteDataItems(array('form_id' => $templateId, 'pid' => $authPid, 'status' => $formStatusList, 'type' => $formType, 'other_details' => false));

        $hasFormAccess = false;
        $formTemplateResults = [];
        $finalformTemplateResults = [];

        $formTemplateList = array();
        $packetTemplateList = array();

        foreach ($formDataItems as $dikey => $dItem) {
            //unset($dItem['template']['template_content']);

            if($dItem['form_type'] == FormController::FORM_LABEL) {
                if (!isset($formTemplateList['t' . $dItem['form_id']])) {
                    $ft = $this->getFormTemplates($dItem['form_id'], "1", "vft.id, vft.template_name, vft.email_template, vft.sms_template, vft.status, vft.to_patient, vft.uid");
                    if (!empty($ft) && is_array($ft) && count($ft) > 0) {
                        $formTemplateList['t' . $dItem['form_id']] = $ft[0];
                    }
                }
                // Set template data
                if (isset($formTemplateList['t' . $dItem['form_id']])) {
                    $dItem['template'] = $formTemplateList['t' . $dItem['form_id']];
                }

                $isFormAssigned = $this->isFormAssigned($dItem['template'], $authPid);
                $formTemplateItem = isset($dItem['form_items']) && count($dItem['form_items']) > 0 ? $dItem['form_items'][0] : array();

                if($isFormAssigned === true && !empty($formTemplateItem)) {
                    $formTemplateResults[] = $dItem;
                    $hasFormAccess = true;
                }
            } else if($dItem['form_type'] == FormController::PACKET_LABEL) {
                if (!isset($packetTemplateList['t' . $dItem['form_id']])) {
                    $pt = $this->getPacketTemplates($dItem['form_id'], "1", false);
                    if (!empty($pt) && is_array($pt) && count($pt) > 0) {
                        $packetTemplateList['t' . $dItem['form_id']] = $pt[0];
                    }
                }
                // Set template data
                if (isset($packetTemplateList['t' . $dItem['form_id']])) {
                    $dItem['template'] = $packetTemplateList['t' . $dItem['form_id']];
                }
                
                $formTemplateResults[] = $dItem;
                $hasFormAccess = true;
            }
            
        }

        return array(
            "list" => $formTemplateResults,
            "hasAccess" => $hasFormAccess
        );
    }

    public function getToAssignedUserList($formId) {
		$formTemplateData = $this->getFormTemplates($formId);
        $toAssignedList = array();
        $toAssigned = isset($formTemplateData['to_patient']) && !empty($formTemplateData['to_patient']) ? explode("|", $formTemplateData['to_patient']) : array();

        foreach ($toAssigned as $toItem) {
            $userId = explode(":", $toItem);
            if(count($userId) === 1 && !empty($userId[0])) {
                if($userId[0] === "-1") {
                    array_unshift($toAssignedList,array("pid" => -1, "title" => "All Patients"));
                    continue;
                }

                $pData = sqlQuery("SELECT pid, lname, mname, fname, DOB, pubpid from patient_data pd where pid = ?", array($userId[0]));

                if(!empty($pData)) {
                    $toAssignedList[] = $pData;
                }
            }
        }

        return $toAssignedList;
    }

    public function getFormPreview($formId) {
        $formTemplate = $this->getFormTemplates($formId);

        if(!empty($formTemplate)) {
            $formTemplate = $formTemplate[0];

            $tempSchema = isset($formTemplate['template_content']) ? json_decode($formTemplate['template_content'], true) : array();
            $tempData = array();

            return array(
                "schema" => $tempSchema,
                "data" => $tempData,
                "form_details" => array(
                    "form_title" => isset($formTemplate["template_name"]) ? $formTemplate["template_name"] : "",
                    "status" => "preview"
                )
            );
        }

        return false;
    }

	public function getFullFormData($authPid, $itemId, $fileInfo = false) {
        if(empty($authPid) || empty($itemId)) {
            throw new \Exception("Unable to submit form");
        }

        $templateData = $this->getAssignedUserFormData($itemId, $authPid);

        if(empty($templateData) || empty($templateData['full_document'])) {
            throw new \Exception("Unable to submit form");
        }

        $tempSchema = isset($templateData['full_document']) ? json_decode($templateData['full_document'], true) : array();
        $tempData = isset($templateData['template_data']) ? json_decode($templateData['template_data'], true) : array();

        // Validate Fields
        $templateFieldList = array();
        $fieldTree = $this->printAllValues($tempSchema['components']);

        $this->cryptoGen = new CryptoGen();

        if(!empty($tempData['data'])) {
            foreach ($tempData['data'] as $fieldKey => $fieldItems) {
                if(isset($fieldTree[$fieldKey]) && isset($fieldTree[$fieldKey]['type']) && $fieldTree[$fieldKey]['type'] == "file" ) {

                    foreach ($fieldItems as $fKey => $fItem) {
                        if(isset($fItem['document']) && isset($fItem['document']['id'])) {
                            $document_id = $fItem['document']['id'];
                            $d = $this->getFormDocument($document_id);

                            if(!empty($d->filetext)) {
                                if($fileInfo === true) {
                                    $tempData['data'][$fieldKey][$fKey]['categories'] = $d->categories;
                                }
                                $tempData['data'][$fieldKey][$fKey]['originalName'] = $d->document->get_name();
                                $tempData['data'][$fieldKey][$fKey]['url'] = "data:" . $d->document->get_mimetype() . ";base64," . base64_encode($d->filetext);
                            }
                        }
                    }
                    
                }
            }
        }

        $facility = sqlQuery("select * from facility where primary_business_entity = 1 order by id desc limit 1", array());

        $siteTitle = isset($GLOBALS['openemr_name']) ? $GLOBALS['openemr_name'] : "";
        $sitePhone = isset($GLOBALS['support_phone_number']) ? $GLOBALS['support_phone_number'] : "";
        if(!empty($facility)) {
            $siteTitle = $facility['name1'];
            //$siteTitle = str_replace(" - OpenEMR", "", $siteTitle);
            $sitePhone = $facility['phone'];
        }

        return array(
            "schema" => json_decode($templateData['full_document'], true),
            "data" => $tempData,
            "form_details" => array(
                "site_title" => $siteTitle,
                "site_phone" => $sitePhone,
                "form_title" => isset($templateData["template"]["template_name"]) ? $templateData["template"]["template_name"] : "",
                "status" => $templateData["status"]
            )
        );
    }

    public function getFormDocument($document_id) {
        $d = new \Document($document_id);
        $url =  $d->get_url();
        $th_url = $d->get_thumb_url();

        if ($d->get_encrypted() == 1) {
            $filetext = $this->cryptoGen->decryptStandard(file_get_contents($url), null, 'database');
        } else {
            if (!is_dir($url)) {
                $filetext = file_get_contents($url);
            }
        }

        $categoriesList = $d->get_categories();
        $categoriesNameList = array();

        foreach ($categoriesList as $categoriesItem) {
            if(isset($categoriesItem['name'])) {
               $categoriesNameList[] = $categoriesItem['name'];
            }
        }

        $doc = new \stdClass();
        $doc->document = $d;
        $doc->filetext = $filetext;
        $doc->categories = $categoriesNameList;

        return $doc; 
    }

    public function getFormFieldComponent($authPid, $formId) {
        try {
            if(empty($authPid) || empty($formId)) {
                throw new \Exception("Unable to submit form");
            }

            $templateData = $this->getAssignedUserFormData($formId, $authPid);

            if(empty($templateData) || empty($templateData['full_document'])) {
                throw new \Exception("Unable to submit form");
            }

            $tempSchema = isset($templateData['full_document']) ? json_decode($templateData['full_document'], true) : array();
            $tempData = isset($templateData['template_data']) ? json_decode($templateData['template_data'], true) : array();

            // Validate Fields
            $templateFieldList = array();
            $fieldTree = $this->printAllValues($tempSchema['components']);

            return $fieldTree;

        } catch (\Throwable $e) {
            return array();
        }
    }

    public function validateFormData($fieldList = array()) {
        // foreach ($fieldList as $fieldKey => $fieldItem) {
        //     if(isset($fieldItem['validate'])) {
        //         $fieldValidations = $fieldItem['validate'];
        //         echo '<pre>';
        //         print_r($fieldItem['label']);
        //         print_r($fieldValidations);
        //         echo '</pre>';
        //     }
        // }

        // exit();
    }

    public function savePatientForm($bodyData = array(), $bodyAction = "save", $formId, $authPid, $tokenId) {
    	$templateFormData = $this->getAssignedUserFormData($formId, $authPid);
    	$returnItems = [];

    	if(is_array($templateFormData) && !empty($templateFormData) && in_array($templateFormData['status'], array(self::PENDING_LABEL, self::SAVE_LABEL))) {
            $templateSchema = isset($templateFormData['full_document']) ? json_decode($templateFormData['full_document'], true) : array();

            $templateData = isset($templateFormData['template_data']) ? json_decode($templateFormData['template_data'], true) : array();

            // Validate Fields
            $templateFieldList = array();
            $fieldTree = $this->printAllValues($templateSchema['components']);
            foreach ($fieldTree as $cItem) {
                if(isset($cItem['key'])) {
                    $templateFieldList[] = $cItem['key'];
                }
            }

            if(!empty($bodyData)) {
                foreach ($bodyData as $bdKey => $bdValue) {
                    if(!in_array($bdKey, $templateFieldList)) {
                        unset($bodyData[$bdKey]);
                    }
                }
            }


            //Check Previsous Form Status Before submit
            $osFormData = $this->getOnsiteForms($formId, $authPid);
            $osFormData = !empty($osFormData) && count($osFormData) === 1 ? $osFormData[0] : array(); 
            $assocFormItems = array();

            if($bodyAction == "submit_all") {
                $assocFormData = $this->getOnsiteDataItems(array('data_id' => $osFormData['ref_id'], "other_details" => false));
                $assocFormData = !empty($assocFormData) ? reset($assocFormData) : array();
                $assocFormItems = isset($assocFormData['form_items']) ? $assocFormData['form_items'] : array();

                foreach ($assocFormItems as $frow) {
                    if(isset($frow['id']) && !empty($frow['id']) && $formId != $frow['id']) {
                        if($frow['status'] !== self::SAVE_LABEL) {
                            throw new \Exception("Wrong submission", 102);
                        }
                    }
                }
            }

            if(empty($osFormData)) {
                throw new \Exception("Unable to submit form", 102);
            }

            // Form Validation Check
            // $vFormData = $this->getFormValidation(array(
            //     "form_data_id" => $formId,
            //     "pid" => $authPid,
            //     "form_data" => array('data' => $bodyData)
            // ), $fieldTree);

            // if(isset($vFormData['isValid']) && isset($vFormData['errors']) && $vFormData['isValid'] === false) {
            //     if(empty($vFormData['errors'])) {
            //         throw new \Exception("Unable to submit form", 102);
            //     }

            //     throw new \Exception(serialize($vFormData['errors']), 102);
            // } else if(isset($vFormData['error']) && $vFormData['error'] === true) {
            //     throw new \Exception("Unable to submit form", 102);
            // }
            // End

            // Validate File Type
            $fileFieldList = array();

            foreach ($fieldTree as $fItem1) {
                if(isset($fItem1['type']) && $fItem1['type'] == "file") {
                    if(isset($bodyData[$fItem1['key']])) {

                        $fieldFiles = $bodyData[$fItem1['key']];
                        $previousFiles = isset($templateData['data']) && isset($templateData['data'][$fItem1['key']]) ? $templateData['data'][$fItem1['key']] : array();

                        $fileFieldSchema = isset($fieldTree[$fItem1['key']]) ? $fieldTree[$fItem1['key']] : array();

                        if(is_array($fieldFiles) && !empty($fieldFiles)) {
                            foreach ($fieldFiles as $ffKey => $ffItem) {

                                if(isset($ffItem['document'])) {

                                    // Existing doc id
                                    $doc_id = isset($ffItem['document']['id']) ? $ffItem['document']['id'] : "";

                                    foreach ($previousFiles as $pkey => $pItem) {
                                        if(isset($pItem['document'])) {
                                            $pre_doc_id = isset($pItem['document']['id']) ? $pItem['document']['id'] : "";

                                            if($doc_id === $pre_doc_id) {
                                                // Assign data
                                                $bodyData[$fItem1['key']][$ffKey] = $previousFiles[$pkey];

                                                // Unset
                                                unset($previousFiles[$pkey]);
                                            }
                                        }
                                    }

                                    continue;
                                }

                                if(empty($ffItem['url'])) {
                                    throw new \Exception("Incorrect file", 102);
                                }

                                // Process to save documents
                                $fileData = $this->saveFileIntoDocument($ffItem, $fileFieldSchema, $authPid);
                                if(is_array($fileData) && isset($fileData['document'])) {

                                    // Unset file url data
                                    unset($bodyData[$fItem1['key']][$ffKey]['url']);

                                    // Set document data
                                    $bodyData[$fItem1['key']][$ffKey]['document'] = $fileData['document'];
                                    
                                    // if(!empty($fileData['filename'])) {
                                    //     $bodyData[$fItem1['key']][$ffKey]['name'] = $fileData['filename'];
                                    //     $bodyData[$fItem1['key']][$ffKey]['originalName'] = $fileData['filename'];
                                    // }
                                    
                                }
                            }
                        }

                        // Remove document files
                        try {
                            if(!empty($previousFiles)) {
                                foreach ($previousFiles as $pItem) {
                                    if(isset($pItem['document'])) {
                                        $pre_doc_id = isset($pItem['document']['id']) ? $pItem['document']['id'] : "";
                                        if(!empty($pre_doc_id)) {
                                            $this->delete_document($pre_doc_id, true);
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            throw new \Exception("Unable to submit form", 102);
                        }
                    }

                }
            }

            try {
                $binds = array();

                if($bodyAction == "fill") {
                    $sqlRes = self::updateOnsiteForms(array(
                        "template_data" => json_encode(array('data' => $bodyData))
                    ), $formId);

                    $returnItems[] = array(
                        "status" => self::PENDING_LABEL
                    );

                } else if($bodyAction == "save") {
                    $sqlRes = self::updateOnsiteForms(array(
                    	"status" => self::SAVE_LABEL,
                    	"template_data" => json_encode(array('data' => $bodyData))
                    ), $formId);

                    $returnItems[] = array(
                        "status" => self::SAVE_LABEL
                    );
                } else if($bodyAction == "submit" || $bodyAction == "submit_all") {
                    
                    // Update Onasite
                    $sqlRes = self::updateOnsiteForms(array(
                        "status" => self::SAVE_LABEL,
                        "template_data" => json_encode(array('data' => $bodyData))
                    ), $formId);


                    $tData = isset($templateFormData['template']) ? $templateFormData['template'] : array();
                    $tName = isset($tData['template_name']) ? $tData['template_name'] : "";

                    // Patient Form PDF
                    //$this->saveFormPDF($formId, $authPid, $tName);


                    $sqlRes = self::updateOnsiteForms(array(
                        "status" => self::SUBMIT_LABEL,
                        "received_date" => "CURRENT_TIMESTAMP"
                    ), $formId);


                    if($bodyAction == "submit_all") {
                        foreach ($assocFormItems as $frow) {
                            if(isset($frow['id']) && !empty($frow['id']) && $formId != $frow['id']) {

                                // Patient Form PDF
                                //$this->saveFormPDF($frow['id'], $authPid, $tName);

                                $sqlRes = self::updateOnsiteForms(array(
                                    "status" => self::SUBMIT_LABEL,
                                    "received_date" => "CURRENT_TIMESTAMP"
                                ), $frow['id']);

                            }
                        }
                    }

                    $expiry = new DateTime('NOW');

                    sqlQueryNoLog("UPDATE `onetime_auth` SET `expires` = ?  WHERE `pid` = ? AND `id` = ?", array($expiry->format('U'), $authPid, $tokenId));

                    $returnItems[] = array(
                        "status" => self::SUBMIT_LABEL
                    );

                    
                    // Generate patient form pdf
                    exec("php " . dirname(__FILE__) . "/../../interface/portal/lib/generate_form_pdf.php " . $formId . "  > /dev/null 2>&1 &");
                }
            } catch (\Throwable $e) {
                throw new \Exception("Unable to submit form", 102);
            } 
        }

        return $returnItems;
    }

    public function sendFormToken($templateId, $formDataId, $dataType = "form", $pid, $method = "", $emailTemplate = "", $smsTemplate = "", $shortenLink = false) {
        
        try {

            if(empty($formDataId)) {
                throw new \Exception("Invalid Data ID");
            }

            if($formDataId == "getlink") {
                $formlogData = $this->getOnsiteDataItems(array('form_id' => $templateId, 'pid' => $pid, 'status' => array(FormController::SAVE_LABEL, FormController::PENDING_LABEL)), "order by vof.created_date desc limit 1");

                $formlogData = !empty($formlogData) ? reset($formlogData) : array();

                if(!empty($formlogData) && isset($formlogData['form_data_id'])) {
                    $formDataId = $formlogData['form_data_id'];
                } else {
                    $formDataId = "new";
                }
            }

            $formTemplates = array();
            $packetTemplates = array();
            $defaultEmailTemplate = "";
            $defaultSMSTemplate = "";
            $tokenExpireTime = $this->tokenExpireTime;

            if($dataType == FormController::FORM_LABEL) {
                $formTemplates = $this->getFormTemplates($templateId);

                if(!empty($formTemplates) && count($formTemplates) === 1) {
                    $tokenExpireTime = !empty($formTemplates) && !empty($formTemplates[0]['expire_time']) ? $formTemplates[0]['expire_time'] : $this->tokenExpireTime;
                    $defaultEmailTemplate = isset($formTemplates[0]['email_template']) ? $formTemplates[0]['email_template'] : "";
                    $defaultSMSTemplate = isset($formTemplates[0]['sms_template']) ? $formTemplates[0]['sms_template'] : "";
                }
                
            } else if($dataType == FormController::PACKET_LABEL) {
                $packetTemplates = $this->getPacketTemplates($templateId);
                
                if(!empty($packetTemplates) && count($packetTemplates) === 1) {
                    $formTemplates = $packetTemplates[0]['form_items'];

                    $tokenExpireTime = !empty($packetTemplates) && !empty($packetTemplates[0]['expire_time']) ? $packetTemplates[0]['expire_time'] : $this->tokenExpireTime;

                    $defaultEmailTemplate = isset($packetTemplates[0]['email_template']) ? $packetTemplates[0]['email_template'] : "";
                    $defaultSMSTemplate = isset($packetTemplates[0]['sms_template']) ? $packetTemplates[0]['sms_template'] : "";
                }
            }

            // FormTemplate data not found
            if(empty($formTemplates)) {
                throw new \Exception("Form Template Not Found");
            }

            // Check Template is empty
            foreach ($formTemplates as $fTemplate) {
                $tContent = isset($fTemplate['template_content']) ? $fTemplate['template_content'] : "";
                if(empty($tContent)) {
                    throw new \Exception("Empty Template Content");
                }
            }

            $formTemplate1 = $this->getFormTemplates($templateId);
            $tokenExpireTime = !empty($formTemplate1) && !empty($formTemplate1[0]['expire_time']) ? $formTemplate1[0]['expire_time'] : $this->tokenExpireTime;
            
            if($formDataId === "new") {
                // Generate Token
                $parameters = [
                    'pid' => $pid,
                    'redirect_link' => "",
                    'expiry_interval' => $tokenExpireTime,
                ];
                $service = new OneTimeAuth();
                $oneTime = $service->createPortalOneTime($parameters);
            } else if(!empty($formDataId) && !empty($dataType)) {
                
                $formlogData = $this->getOnsiteDataItems(array('data_id' => $formDataId), "order by vof.created_date desc limit 1");

                $formlogData = !empty($formlogData) ? reset($formlogData) : array();
                $tokenData = !empty($formlogData) && isset($formlogData['token']) ? $formlogData['token'] : array();

                if(empty($tokenData) || empty($tokenData['onetime_token']) || empty($tokenData['onetime_token_id'])) {
                    throw new \Exception("Invalid Data ID");
                }

                $expTime = new DateTime('NOW');
                $expTime->add(new DateInterval($tokenExpireTime));
                
                sqlQueryNoLog("UPDATE onetime_auth SET expires = ? WHERE id = ? AND pid = ?;", array($expTime->format('U'), $tokenData['onetime_token_id'], $pid), true);

                try {
                    $oneTime = $this->verifyToken($tokenData['onetime_token']);
                } catch (\Throwable $e) {
                    throw new \Exception("Incorrect token value");
                }

                $oneTime['onetime_token'] = $tokenData['onetime_token'];
            }

            $returnData = array('oneTime' => $oneTime);
            $actionStatus = false;
            
            if(isset($oneTime['onetime_token']) && !empty($oneTime['onetime_token'])) {
                $tokenUrl = $this->getDocumentFormLink($oneTime['onetime_token']);
                $returnData['oneTime']['encoded_link'] = $tokenUrl;

				// $formTemplate = $this->getFormTemplates($templateId);
				// if(is_array($formTemplate) && !empty($formTemplate) && count($formTemplate) === 1) {
				// 	$formTemplate = $formTemplate[0];
				// } else {
				// 	$formTemplate = array();
				// }

                // if(empty($formTemplate)) {
                //     throw new \Exception("Form Template Not Found");
                // }

				$isFormAssigned = $this->isFormAssigned($formTemplates, $pid);

                if($isFormAssigned === false) {
                    throw new \Exception("Patient not have access of form");
                }

                // $templateContent = isset($formTemplate['template_content']) ? $formTemplate['template_content'] : "";

                // if(empty($templateContent)) {
                //     throw new \Exception("Empty Template Content");
                // }

                // Generate Shorten URL
                $shortenUrl = $tokenUrl;
                if($shortenLink === true) {
                    $shortenUrl = ShortenLink::getLink($tokenUrl);
                }
                $returnData['shortenUrl'] = $shortenUrl;
                $notifLog = array();

                // Send Email & SMS
                if(in_array($method, array("email", "sms", "both"))) {

                    $defaultEmailTemplate = isset($formTemplate['email_template']) ? $formTemplate['email_template'] : "";
                    $defaultSMSTemplate = isset($formTemplate['sms_template']) ? $formTemplate['sms_template'] : "";

                    if(!empty($emailTemplate)) $defaultEmailTemplate = $emailTemplate;
                    if(!empty($smsTemplate)) $defaultSMSTemplate = $smsTemplate;

                    $templateData = array();

                    if($method == "email" || $method == "both") {
                        $email_message_list = new \wmt\Options('Email_Messages');
                        foreach ($email_message_list->list as $tItem) {
                            if(isset($tItem['option_id']) && $tItem['option_id'] == $defaultEmailTemplate) {
                                $templateData["email"] = array(
                                    "template_id" => $tItem['option_id'],
                                    "subject" => $tItem['notes']
                                );
                                break;
                            }
                        }
                    } 

                    if($method == "sms" || $method == "both") {
                        $sms_message_list = new \wmt\Options('SMS_Messages');
                        foreach ($sms_message_list->list as $tItem) {
                            if(isset($tItem['option_id']) && $tItem['option_id'] == $defaultSMSTemplate) {
                                $templateData["sms"] = array(
                                    "template_id" => $tItem['option_id'],
                                    "subject" => $tItem['notes']
                                );
                                break;
                            }
                        }
                    }

                    $pat_data = Reminder::getPatientData($pid);

                    // preformat commonly used data elements
                    $pat_name = ($pat_data['title'])? $pat_data['title'] : "";
                    $pat_name .= ($pat_data['fname'])? $pat_data['fname'] : "";
                    $pat_name .= ($pat_data['mname'])? substr($pat_data['mname'],0,1).". " : "";
                    $pat_name .= ($pat_data['lname'])? $pat_data['lname'] : "";

                    if(empty($pat_data)) {
                        throw new \Exception("Empty Patient Data");
                    }

                    if($method == "email" || $method == "both") {
                        $email_messaging_disabled = ($pat_data['hipaa_allowemail'] != 'YES' || (empty($pat_data['email']) && !$GLOBALS['wmt::use_email_direct']) || (empty($pat_data['email_direct']) && $GLOBALS['wmt::use_email_direct'])) ? true : false;
                        $email_direct = $GLOBALS['wmt::use_email_direct'] ? $pat_data['email_direct'] : $pat_data['email'];

                        if($pat_data['hipaa_allowemail'] != 'YES') {
                            throw new \Exception("Check if patient has allowed to receive email.");
                        }

                        if(empty($email_direct)) {
                            throw new \Exception("Invalid patient email.");
                        }

                        $emailTemplateData  = isset($templateData['email']) ? $templateData['email'] : array();

                        if(empty($emailTemplateData)) {
                            throw new \Exception("Empty Template Data");
                        }

                        global $pf_token_url;
                        $pf_token_url = $shortenUrl;

                        $msgContents = Reminder::getFullMessage($pid, $emailTemplateData['template_id']);
                        $msgSubject = Reminder::getSubject($pid, $email_message_list, $emailTemplateData['template_id']);

                        $msgContent = $msgContents['content_html'];
                        //$msgContent = str_replace('{{token_url}}', $shortenUrl, $msgContents['content_html']);

                        $eItem = array(
                            'pid' => $pid,
                            'data' => array(
                                'email' => $email_direct,
                                'template' => $emailTemplateData['template_id'],
                                'subject' => $msgSubject,
                                'patient' => $pat_name,
                                'html' => $msgContent,
                                'text' => $msgContent,
                                'request_data' => array(),
                                'files' => array(),
                            ));
                        
                        $eData = EmailMessage::TransmitEmail(
                                array($eItem['data']), 
                                array('pid' => $eItem['pid'], 'logMsg' => true)
                            );

                        if(count($eData) === 1 && $eData[0]['status'] === false) {
                            if(!empty($eData[0]['errors'])) {
                                $returnData['errors'][] = "EMAIL - " . $eData[0]['to'] . ": ". implode(",",$eData[0]['errors']) . " (FAILED)";
                            }
                        }

                        if(count($eData) === 1 && $eData[0]['status'] === true) {
                            $returnData['success'][] = "EMAIL - " . $eData[0]['to'] . " (SENT)";
                            $actionStatus = true;

                            if(isset($eData[0]['data']) && !empty($eData[0]['data'])) {
                                foreach ($eData[0]['data'] as $eDataItem) {
                                    if(isset($eDataItem['msgid']) && !empty($eDataItem['msgid'])) {
                                        $notifLog[] = array(
                                            "uniqueid" => $eDataItem['msgid'],
                                            "type" => "message_log"
                                        );
                                    }
                                }
                            }
                        }
                    } 

                    if($method == "sms" || $method == "both") {
                        $configList = Reminder::getConfigVars();

                        $pat_phone = isset($pat_data['phone_cell']) && !empty($pat_data['phone_cell']) ? preg_replace('/[^0-9]/', '', $pat_data['phone_cell']) : "";

                        $isEnable = $pat_data['hipaa_allowsms'] != 'YES' || empty($pat_data['phone_cell']) ? true : false;

                        if($pat_data['hipaa_allowsms'] != 'YES') {
                            throw new \Exception("Check if patient has allowed to receive sms.");
                        }

                        if(empty($pat_phone)) {
                            throw new \Exception("Invalid patient phone number");
                        }

                        $smsTemplateData  = isset($templateData['sms']) ? $templateData['sms'] : array();

                        if(empty($smsTemplateData)) {
                            throw new \Exception("Empty Template Data");
                        }

                        global $pf_token_url;
                        $pf_token_url = $shortenUrl;

                        $msgContents = Reminder::getFullMessage($pid, $smsTemplateData['template_id']);

                        $msgContent = $msgContents['content'];
                        //$msgContent = str_replace('{{token_url}}', $tokenUrl, $msgContents['content']);

                        $to_pat_phone = MessagesLib::getPhoneNumbers($pat_phone);
                        $to_pat_phone =  $to_pat_phone['msg_phone'];

                        if(empty($to_pat_phone)) {
                            throw new \Exception("Invalid Number");
                        }

                        $pItem = array(
                        'pid' => $pid,
                        'data' => array(
                            'from' => $configList->send_phone,
                            'to_send' => array($to_pat_phone),
                            'template' => $smsTemplateData['template_id'],
                            'patient' => $pat_name,
                            'text' => $msgContent
                        ));

                        $pData = ActionEvent::TransmitSMS(
                            array($pItem['data']), 
                            array('pid' => $pid, 'logMsg' => true)
                        );

                        if(is_array($pData) && count($pData) >= 1) {
                            $responce = $pData[0];
                            if(isset($responce) && isset($responce['errors']) && !empty($responce['errors'])) {
                                $returnData['errors'][] = "SMS - " . implode(",",$responce['to']) . ": ". implode(",",$responce['errors']) . " (FAILED)";
                            }
                        }

                        if(count($pData) === 1 && $pData[0]['status'] === true) {
                            $returnData['success'][] =  "SMS - " . implode(",",$pData[0]['to']) . " (SENT)";
                            $actionStatus = true;

                            if(isset($pData[0]['data']) && !empty($pData[0]['data'])) {
                                foreach ($pData[0]['data'] as $pDataItem) {
                                    if(isset($pDataItem['msgid']) && !empty($pDataItem['msgid'])) {
                                        $notifLog[] = array(
                                            "uniqueid" => $pDataItem['msgid'],
                                            "type" => "message_log"
                                        );
                                    }
                                }
                            }
                            
                        }
                    }

                }
                

                if($method == "link") {
                    $actionStatus = true;

                    $notifLog[] = array(
                        "uniqueid" => 0,
                        "type" => "link"
                    );
                }

                if($actionStatus === true && $formDataId == "new") {
                    $newPacketId = 0;
                    $newFormId = 0;
                    $createdBy = isset($_SESSION['authUserID']) ? $_SESSION['authUserID'] : "SYSTEM";
                    
                    $newRefId = sqlInsert("INSERT INTO `vh_form_data_log` (`form_id`, `type`, `created_by`) VALUES(?, ?, ?)", array($templateId, $dataType, $createdBy));
                    

                    foreach ($formTemplates as $formTemplate) {
                        $templateContent = isset($formTemplate['template_content']) ? $formTemplate['template_content'] : "";

                        sqlInsert("INSERT INTO `vh_onsite_forms` (pid, form_id, ref_id, created_date, doc_type, denial_reason, status, full_document) VALUES(?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)", array($pid, $formTemplate['id'], $newRefId, "patient", "", self::PENDING_LABEL, $templateContent));
                    }

                    if(!empty($newRefId)) {
                        sqlInsert("INSERT INTO `vh_onetimetoken_form_log` ( `uid`, `onetime_token`, `onetime_token_id`, `ref_id`) VALUES ( ?, ?, ?, ?)", array($_SESSION['authUserID'], $oneTime['onetime_token'], $oneTime['tokenId'], $newRefId));

                        $returnData['form_data_id'] = $newRefId;
                    }
                }

                if(!empty($formDataId) && is_numeric($formDataId)) {
                    $returnData['form_data_id'] = $formDataId;
                }

                // Log message id
                if(!empty($notifLog) && !empty($returnData['form_data_id'])) {
                    foreach ($notifLog as $notifLogItem) {
                        if(isset($notifLogItem['uniqueid']) && $notifLogItem['uniqueid'] >= 0) {
                            self::saveFormReminderlog(array(
                                "form_data_id" => $returnData['form_data_id'],
                                "table_name" => $notifLogItem['type'],
                                "uniqueid" => $notifLogItem['uniqueid']
                            ));
                        }
                    }
                }

                return $returnData;
            } else {
                throw new \Exception("Error creating token");
            }
        
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getDocumentFormLink($onetime_token = "") {
        return $this->formPortalFormUrl . urlencode($onetime_token);
    }

    private function printAllValues($arr, $fieldKey = '', $parentItem = array(), $fieldList = array()) {
        if (!is_array($arr)) {
            // don't process this

            if($fieldKey == "key" && isset($parentItem['type'])) {
                $fieldList[$arr] = $parentItem;
            }
            return $fieldList;
        }

        foreach ($arr as $k => $v) {
            $childFieldList = $this->printAllValues($v, $k, $arr, $fieldList);

            if(!empty($childFieldList)) {
                $fieldList = $childFieldList;
            }
        }

        return $fieldList;
    }

    private function saveFileIntoDocument(&$fileItem = array(), $fileSchema = array(), $pid = "") {
        try {

            $owner = $GLOBALS['userauthorized'];
            $category = $this->defaultDocumentCategory;

            if(empty($fileItem) || empty($pid) || empty($category) || empty($fileSchema)) {
                throw new \Exception("Unable to save files");
            }

            $fname = $fileItem['name'];
            $ftype = $fileItem['type'];
            $ftmp_name = $fileItem['url'];
            $fsize = $fileItem['size'];

            if(empty($fileSchema) || !isset($fileSchema['key']) || empty($fileSchema['key'])) {
                throw new \Exception("Unable to save files");
            }

            if(!empty($fileSchema)) {
                $f_filePattern = isset($fileSchema['filePattern']) && !empty($fileSchema['filePattern']) ? explode(",", $fileSchema['filePattern']) : array();
                $f_fileMinSize = isset($fileSchema['fileMinSize']) && !empty($fileSchema['fileMinSize']) ? $this->convertToBytes($fileSchema['fileMinSize']) : $this->convertToBytes("1KB");
                $f_fileMaxSize = isset($fileSchema['fileMaxSize']) && !empty($fileSchema['fileMaxSize']) ? $this->convertToBytes($fileSchema['fileMaxSize']) : $this->convertToBytes("20MB");

                $f_fileKey = isset($fileSchema['key']) && !empty($fileSchema['key']) ? $fileSchema['key'] : "";
                

                if(!empty($f_filePattern)) {
                    $f_filePattern = array_map("trim", $f_filePattern);
                    $f_filePattern = array_map(function($item) {
                                        return trim($item, '.');
                                    }, $f_filePattern);

                    $file_ext = pathinfo($fname, PATHINFO_EXTENSION);
                    if(!in_array($file_ext, $f_filePattern)) {
                        throw new \Exception("Unable to save files");
                    }
                }
            }

            if(!empty($ftmp_name)) {
                $file_content = str_replace('data:' . $ftype . ';base64,', '', $ftmp_name);
                $file_content = str_replace(' ', '+', $file_content);
                $file_data = base64_decode($file_content);

                if(empty($file_data)) {
                    throw new \Exception("Unable to save files");
                }

                $filePath = $GLOBALS['OE_SITE_DIR'] . "/documents/temp/" . $fname;
                $fileInfo = $this->cleanFileName($filePath, $f_fileKey);

                $filePath = isset($fileInfo['path']) ? $fileInfo['path'] : "";
                if(isset($fileInfo['filename']) && !empty($fileInfo['filename'])) {
                    $fname = $fileInfo['filename'];
                }

                $fileSaved = file_put_contents($filePath, $file_data);
                $fsize = filesize($filePath);

                if($f_fileMaxSize < $fsize) {
                    throw new \Exception("Incorrect maximum file size");
                }

                if($f_fileMinSize > $fsize) {
                    throw new \Exception("Incorrect minimum file size");
                }

                if(!$fileSaved) {
                    throw new \Exception("Unable to save files");
                }

                $data = addNewDocument(
                    $fname,
                    $ftype,
                    $filePath,
                    '',
                    $fsize,
                    $owner,
                    $pid,
                    $category,
                    '',
                    '',
                    true
                );

                // Delete temp file
                unlink($filePath);

                if(is_array($data) && isset($data['doc_id']) && !empty($data['doc_id'])) {
                    $data = array(
                        "filename" => $fname,
                        "document" => array(
                            "id" => $data['doc_id']
                        )
                    );
                } else {
                    throw new \Exception("Unable to save files");
                }
                
                return $data;
            } else {
                throw new \Exception("Unable to save files");
            }
        } catch (\Throwable $e) {

            // Delete temp file
            unlink($filePath);

            throw new \Exception($e->getMessage(), 101);
        }

        return false;
    }

    private function delete_document($document, $deletePhysicalFile = false) {
    	if(empty($document)) {
    		return false;
    	}

        if($deletePhysicalFile === true) {
            $d = new \Document($document);
            if($d) {
                $dUrl = $d->get_url_path().$d->get_url_file();
                if(file_exists($dUrl) && !is_dir($dUrl)) {
                    unlink($dUrl);
                }
            }
            sqlQueryNoLog("DELETE FROM `documents` WHERE id = ?", [$document]);
        } else {
            sqlQueryNoLog("UPDATE `documents` SET `deleted` = 1 WHERE id = ?", [$document]);
        }

        $this->row_delete("categories_to_documents", "document_id = '" . add_escape_custom($document) . "'");
        $this->row_delete("gprelations", "type1 = 1 AND id1 = '" . add_escape_custom($document) . "'");
    }

    private function row_delete($table, $where)
    {
        $tres = sqlStatementThrowException("SELECT * FROM " . escape_table_name($table) . " WHERE $where");
        $count = 0;
        while ($trow = sqlFetchArray($tres)) {
            $logstring = "";
            foreach ($trow as $key => $value) {
                if (! $value || $value == '0000-00-00 00:00:00') {
                    continue;
                }

                if ($logstring) {
                    $logstring .= " ";
                }

                $logstring .= $key . "= '" . $value . "' ";
            }

            \OpenEMR\Common\Logging\EventAuditLogger::instance()->newEvent("delete", "", "", 1, "$table: $logstring");
            ++$count;
        }

        if ($count) {
            $query = "DELETE FROM " . escape_table_name($table) . " WHERE $where";
            sqlQueryNoLog($query);
        }
    }

    private function convertToBytes($from) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $number = substr($from, 0, -2);
        $suffix = strtoupper(substr($from,-2));

        //B or no suffix
        if(is_numeric(substr($suffix, 0, 1))) {
            return preg_replace('/[^\d]/', '', $from);
        }

        $exponent = array_flip($units)[$suffix] ?? null;
        if($exponent === null) {
            return null;
        }

        return $number * (1024 ** $exponent);
    }

    public function size_as_kb($size) {
        if ($size < 1024) {
            return "{$size} bytes";
        } elseif ($size < 1048576) {
            $size_kb = round($size/1024);
            return "{$size_kb} KB";
        } else {
            $size_mb = round($size/1048576, 1);
            return "{$size_mb} MB";
        }
    }

    private function cleanFileName($file_name, $file_prefix = ""){ 
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION); 
        $file_name_str = pathinfo($file_name, PATHINFO_FILENAME); 
        $file_dirname_str = pathinfo($file_name, PATHINFO_DIRNAME);  
         
        // Replaces all spaces with hyphens. 
        $file_name_str = str_replace(' ', '-', $file_name_str); 
        // Removes special chars. 
        $file_name_str = preg_replace('/[^A-Za-z0-9\-\_]/', '', $file_name_str); 
        // Replaces multiple hyphens with single one. 
        $file_name_str = preg_replace('/-+/', '-', $file_name_str); 
         
        $clean_file_name = uniqid(( !empty($file_prefix) ? $file_prefix . "_" : "" )) . '.' . $file_ext; 
         
        return array(
            'filename' => $clean_file_name,
            'path' => $file_dirname_str . '/' . $clean_file_name
        ); 
    }

    public function getFormPDF($formDataId, $pid, $form_id = '', $otherData = array()) {
        $siteUrl = sprintf(
            "%s://%s%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
            $_SERVER['SERVER_NAME'],
            $GLOBALS['webroot']
          );

        $cURLConnection = curl_init();
        $headers = array(
            'Origin: ' . $siteUrl,
        );

        $queryStr = array();

        if(!empty($formDataId)) {
            $queryStr['formDataId'] = $formDataId;
        }

        if(!empty($form_id)) {
            $queryStr['formId'] = $form_id;
        }

        if(!empty($pid)) {
            $queryStr['pid'] = $pid;
        }

        if(isset($otherData['submitted_on']) && !empty($otherData['submitted_on'])) {
            $queryStr['submittedOn'] = $otherData['submitted_on'];
        }

        if(isset($otherData['pubpid']) && !empty($otherData['pubpid'])) {
            $queryStr['chartNo'] = $otherData['pubpid'];
        }

        $url = $GLOBALS['fm_form_portal_url'] . '/form/pdf';

        if(!empty($queryStr)) {
            $url .= '?' . http_build_query($queryStr);
        }

        curl_setopt($cURLConnection, CURLOPT_URL, $url);
        curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURLConnection, CURLOPT_HTTPHEADER, $headers);

        $pageResponce = curl_exec($cURLConnection);
        curl_close($cURLConnection);

        return !empty($pageResponce) ? json_decode($pageResponce, true) : array();
    }

    public function getFormValidation($payload = array(), $fieldTree) {
        $siteUrl = sprintf(
            "%s://%s%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
            $_SERVER['SERVER_NAME'],
            $GLOBALS['webroot']
          );

        if(isset($payload['form_data']) && isset($payload['form_data']['data'])) {
            foreach ($fieldTree as $fItem1) {
                if(isset($fItem1['type']) && $fItem1['type'] == "file") {
                    if(isset($payload['form_data']['data'][$fItem1['key']])) {
                        $docItems = $payload['form_data']['data'][$fItem1['key']];

                        foreach ($docItems as $docKey => $doci) {
                            if(isset($payload['form_data']['data'][$fItem1['key']][$docKey]['url'])) {
                                $payload['form_data']['data'][$fItem1['key']][$docKey]['url'] = "";
                            } 
                        }
                    }
                }
            }
        }

        $cURLConnection = curl_init();
        $headers = array(
            'Origin: ' . $siteUrl,
        );

        curl_setopt($cURLConnection, CURLOPT_URL, $GLOBALS['fm_form_portal_url'] . '/form/validation');

        $payload = json_encode( $payload );
        curl_setopt( $cURLConnection, CURLOPT_POSTFIELDS, $payload );
        curl_setopt( $cURLConnection, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt( $cURLConnection, CURLOPT_RETURNTRANSFER, true);

        $pageResponce = curl_exec($cURLConnection);

        if (curl_errno($cURLConnection)) {
            $errorMsg = curl_error($cURLConnection);

            if(!empty($errorMsg)) {
                return array(
                    'error' => true,
                    'messages' => $errorMsg 
                );
            }
        }

        curl_close($cURLConnection);

        return !empty($pageResponce) ? json_decode($pageResponce, true) : array('error' => true, 'messages' => 'Something went wrong');
    }

    public function saveFormPDF($formId, $authPid, $docName = "", $otherData = array()) {
        try{
            $pdfResponce = $this->getFormPDF($formId, $authPid, "", $otherData);
            if(empty($pdfResponce) || !isset($pdfResponce['content']) || empty($pdfResponce['content'])) {
                throw new \Exception("Unable to submit form", 102);
            }

             // Replaces all spaces with hyphens. 
            $file_name_str = str_replace(' ', '_', $docName); 
            // Removes special chars. 
            $file_name_str = preg_replace('/[^A-Za-z0-9\-\_]/', '', $file_name_str); 
            // Replaces multiple hyphens with single one. 
            $file_name_str = preg_replace('/-+/', '-', $file_name_str); 

            $formPDFSchema = json_decode('{"label":"Form PDF","filePattern":".pdf","fileMinSize":"1KB","fileMaxSize":"100MB","key":"form_' . strtolower($file_name_str) . '","type":"file","input":true}', true);
            $formPDFData = array(
                'name' => $docName . '.pdf',
                'type' => $pdfResponce['type'],
                'url' => $pdfResponce['content'],
                'size' => 0 
            );

            $fileData = $this->saveFileIntoDocument($formPDFData, $formPDFSchema, $authPid);
            if(is_array($fileData) && isset($fileData['document'])) {
                $doc_id = isset($fileData['document']['id']) ? $fileData['document']['id'] : "";

                if(empty($doc_id)) {
                    throw new \Exception("Unable to submit form", 102);
                }

                return sqlInsert("INSERT INTO `vh_form_documents_log` ( `form_id`, `doc_id`) VALUES ( ?, ? )", array($formId, $doc_id));
            }
        } catch (\Throwable $e) {
            throw new \Exception("Unable to submit form", 102);
        }

        return false;
    }

    public static function getTokenLink($html_tags, &$elements) {
        global $pf_token_url;
        $tags_list = array('pf_token_url');

        // do html substitutions
        foreach ($tags_list as $key => $tList) {
            if (in_array($tList, $html_tags)) {
                $value = (array_key_exists($tList, $elements)) ? $elements[$tList] : '';

                if(empty($value)) {
                    $pf_token_url = isset($pf_token_url) && !empty($pf_token_url) ? $pf_token_url : "";

                    if(!empty($pf_token_url)) {
                        $elements[$tList] = $pf_token_url;
                    }
                }
            }
        }
    }

    public function getFormLinkByForm($formDataId) {
        if(empty($formDataId)) {
            return false;
        }

        $formlogData = $this->getOnsiteDataItems(array('data_id' => $formDataId));
        $formlogData = !empty($formlogData) ? reset($formlogData) : array();
        $tokenData = !empty($formlogData) && isset($formlogData['token']) ? $formlogData['token'] : array();

        if(empty($tokenData) || empty($tokenData['onetime_token']) || empty($tokenData['onetime_token_id'])) {
            return false;
        }

        $tokenUrl = $this->getDocumentFormLink($tokenData['onetime_token']);
        $shortenUrl = ShortenLink::getLink($tokenUrl);

        return array(
            'form_link' => $tokenUrl,
            'shorten_url' => $shortenUrl
        );
    }

    // Packets

    // Get Form Template Details
    public function getPacketTemplates($packetId = "", $status = "", $formListRequired = true) {
        $strWhere = "";
        $binds = array();

        if(!empty($packetId)) {
            $strWhere .= " and vfp.id = ? ";
            $binds[] = $packetId;
        }

        if(!empty($status)) {
            $strWhere .= " and vfp.status = ? ";
            $binds[] = $status;
        }

        $pResult = sqlStatementNoLog("SELECT vfp.*, vfp.name as template_name, (select GROUP_CONCAT(vpl2.form_id) from vh_packet_link vpl2 where vpl2.packet_id = vfp.id) as form_ids from vh_form_packets vfp where vfp.id != '' " . $strWhere . " order by vfp.id desc", $binds);

        $pReturn = [];
        while ($row = sqlFetchArray($pResult)) {

            $row['form_items'] = array();

            if ($formListRequired === true && isset($row['form_ids']) && $row['form_ids'] != "") {
                $formIdList = array_map('trim', explode(",", $row['form_ids']));
                if (!empty($formIdList)) {
                    $formItemList = $this->getFormTemplatesByIds($formIdList);
                    if (!empty($formItemList) && is_array($formItemList)) {
                        $row['form_items'] = $formItemList;
                    }
                }
                
            }

            $pReturn[] = $row;
        }

        return $pReturn;
    }

    // Get Packet Template List
    public function getPacketTemplateList($opts = array()) {
        $packetId = isset($opts['packet_id']) ? $opts['packet_id'] : "";
        $packetTemplateList = $this->getPacketTemplates($packetId, "1", false);

        $packetTemplateResults = [];
        foreach ($packetTemplateList as $packetTemplateItem) {
            $isFormAssigned = true;

            if($isFormAssigned === true) {
                $packetTemplateResults[] = $packetTemplateItem;
            }
        }

        return $packetTemplateResults;
    }

    public function getFormIdType($formId) {
        $formType = FormController::FORM_LABEL;

        if(substr($formId, 0, 1) == "f") {
            $formId = substr_replace($formId, "", 0, 1);
            $formType = FormController::FORM_LABEL;
        } else if(substr($formId, 0, 1) == "p") {
            $formId = substr_replace($formId, "", 0, 1);
            $formType = FormController::PACKET_LABEL;
        }

        return array(
            "formId" => $formId,
            "formType" => $formType
        );
    }

    public function checkMessagingStatus($pid = "", $method = "") {

        if(empty($pid) || empty($method)) {
            return false;
        }

        $pat_data = Reminder::getPatientData($pid);

        if(empty($pat_data)) {
            return false;
        }

        if($method == "email") {
            $email_messaging_disabled = ($pat_data['hipaa_allowemail'] != 'YES' || (empty($pat_data['email']) && !$GLOBALS['wmt::use_email_direct']) || (empty($pat_data['email_direct']) && $GLOBALS['wmt::use_email_direct'])) ? true : false;
            $email_direct = $GLOBALS['wmt::use_email_direct'] ? $pat_data['email_direct'] : $pat_data['email'];

            if(empty($email_direct) || $email_messaging_disabled !== false) {
                return false;
            }

        } else if($method == "sms") {
            $pat_phone = isset($pat_data['phone_cell']) && !empty($pat_data['phone_cell']) ? preg_replace('/[^0-9]/', '', $pat_data['phone_cell']) : "";

            $isEnable = $pat_data['hipaa_allowsms'] != 'YES' || empty($pat_data['phone_cell']) ? true : false;

            if(empty($pat_phone) || $isEnable !== false) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    public function saveFormReminderlog($formData = array()) {
        $formDataId = isset($formData['form_data_id']) ? $formData['form_data_id'] : "";
        $tableName = isset($formData['table_name']) ? $formData['table_name'] : "";
        $uniqueid = isset($formData['uniqueid']) ? $formData['uniqueid'] : "";

        if(empty($formDataId) || empty($tableName) || $uniqueid < 0) {
            return false;
        }

        return sqlInsert("INSERT INTO `vh_form_reminder_log` (`ref_id`, `type`, `uniqueid`) VALUES(?, ?, ?)", array($formDataId, $tableName, $uniqueid));
    }

    public function saveReminderFormReminderlog($formData = array()) {
        $formDataId = isset($formData['form_data_id']) ? $formData['form_data_id'] : "";
        $tableName = isset($formData['table_name']) ? $formData['table_name'] : "";
        $uniqueid = isset($formData['uniqueid']) ? $formData['uniqueid'] : "";

        if(empty($formDataId) || empty($tableName) || empty($uniqueid)) {
            return false;
        }

        $notifyData = sqlQuery("SELECT * from `vh_form_reminder_log` vgrl where vgrl.`uniqueid` = ? and vgrl.`type` = ? limit 1", array($uniqueid, 'notif_log'));

        if(empty($notifyData)) {
            return sqlInsert("INSERT INTO `vh_form_reminder_log` (`ref_id`, `type`, `uniqueid`) VALUES(?, ?, ?)", array($formDataId, $tableName, $uniqueid));
        }

        return false;
    }

    public function getReminderlog($formDataId = "") {
        $returnData = array();

        if(!empty($formDataId)) {
            $remlogResult = sqlStatementNoLog("SELECT * from vh_form_reminder_log vfrl where ref_id = ? order by created_date asc;", $formDataId);

            while ($rlrow = sqlFetchArray($remlogResult)) {
                $tableName = isset($rlrow['type']) ? $rlrow['type'] : "";
                $uniqueid = isset($rlrow['uniqueid']) ? $rlrow['uniqueid'] : "";

                if($uniqueid < 0) {
                    continue;
                }

                if($tableName == "message_log") {
                    $msgData = sqlQuery("SELECT * from message_log ml where ml.id = ? order by id desc limit 1", array($uniqueid));

                    $returnData[] = array(
                        "type" => $msgData["type"],
                        "datetime" => $msgData["msg_time"],
                        "status" => $msgData["msg_status"]
                    );
                    
                } else if($tableName == "notif_log") {
                    $msgData = sqlQuery("SELECT nl.id, nl.msg_type, nl.sent, ml.id as msg_id, ml.type, ml.msg_time, ml.msg_status from notif_log nl left join message_log ml on ml.id = nl.msg_id where nl.id = ? order by id DESC", array($uniqueid));

                    $msgStatus = " - ";
                    $msgType = " - ";
                    $msgTime = " - ";
                    if(!empty($msgData)) {
                        if($msgData['sent'] == 0 && (empty($msgData['msg_id']) || empty($msgData['msg_status']))) {
                            $msgStatus = "UNSENT";
                        } else if($msgData['sent'] != 0) {
                            $msgStatus = "FAILED";
                        }

                        if(!empty($msgData['msg_id']) && !empty($msgData['msg_status'])) {
                            $msgStatus = $msgData['msg_status'];
                        }

                        $msgType = strtoupper($msgData['msg_type']);
                        if(!empty($msgData['type'])) {
                            $msgType = $msgData['type'];
                        }

                        if(!empty($msgData['msg_time'])) {
                            $msgTime = $msgData["msg_time"];
                        }
                    }

                    $returnData[] = array(
                        "type" => $msgType,
                        "datetime" => $msgTime,
                        "status" => $msgStatus
                    );
                } else if($tableName == "link") {
                    $returnData[] = array(
                        "type" => strtoupper($tableName),
                        "datetime" => $rlrow['created_date'],
                        "status" => "CREATED"
                    );
                }
            }
        }

        return $returnData;
    }

    public function getFormAssocItems($formDataId) {
        $formAssocResult = sqlStatementNoLog("SELECT vof.`form_id`, vfdl.`type`, vfdl.id, vof.id, vof.status, vof.created_date, vof.received_date, vof.pid  from vh_form_data_log vfdl join vh_onsite_forms vof on vof.ref_id = vfdl.id where vof.ref_id = ? order by vof.id ASC;", $formDataId);

        $returnItems = array();
        while ($formassocrow = sqlFetchArray($formAssocResult)) {
            $returnItems[] = $formassocrow;
        }

        return $returnItems;
    }

    public function checkRequiredFieldBeforeSend($formId, $formDataId = "", $dataType = "", $formFieldData = array()) {
        $reqBeforeSendingFieldList = array();
        $reqFieldFormData = array();

        if($dataType == FormController::FORM_LABEL) {
            if(!empty($formId) && $formDataId == "new") {
                $formTemplateData = $this->getFormTemplates($formId);
                $formTemplateData = !empty($formTemplateData) && count($formTemplateData) === 1 ? $formTemplateData[0] : array();

                // Form field data
                $formFieldData = isset($formFieldData[$formId]) ? $formFieldData[$formId]['data'] : array();

                if(!empty($formTemplateData)) {
                    $templateSchema = isset($formTemplateData['template_content']) ? json_decode($formTemplateData['template_content'], true) : array();

                    $templateFieldTree = $this->printAllValues($templateSchema['components']);

                    foreach ($templateFieldTree as $tfItem) {

                        if(isset($tfItem['validate']) && isset($tfItem['validate']['requiredBeforeSending']) && $tfItem['validate']['requiredBeforeSending'] == "1" ) {

                            if(isset($tfItem['conditional']) && isset($tfItem['conditional']['show']) && isset($tfItem['conditional']['when']) && isset($tfItem['conditional']['eq'])) {

                                $crField = false;

                                foreach ($templateFieldTree as $tfItem1) {
                                    if (isset($tfItem1['key']) && $tfItem1['key'] == $tfItem['conditional']['when']) {
                                        if((isset($formFieldData[$tfItem1['key']]) && !empty($formFieldData[$tfItem1['key']]))) {

                                            if( (is_array($formFieldData[$tfItem1['key']]) && in_array($tfItem['conditional']['eq'], $formFieldData[$tfItem1['key']])) || $formFieldData[$tfItem1['key']] == $tfItem['conditional']['eq'] ) {
                                                $crField = true;
                                                break;
                                            }
                                        } else {
                                            $crField = true;
                                            break;
                                        }
                                    }
                                }

                                if ($crField === false) {
                                    continue;
                                }
                            }

                            if((!isset($formFieldData[$tfItem['key']]) || empty($formFieldData[$tfItem['key']]))) {

                                // Set required form data
                                if(!isset($reqFieldFormData[$formTemplateData['id']])) {
                                    $reqFieldFormData[$formTemplateData['id']] = array(
                                        "form" => $formTemplateData       
                                    );
                                }

                                // Set required fields
                                $reqFieldFormData[$formTemplateData['id']]['req_fields'][] = $tfItem['key'];

                                $reqBeforeSendingFieldList[] = $formTemplateData['template_name'] . " - " . $tfItem['label'];
                            }
                        }
                    }
                }
            }
        } else if($dataType == FormController::PACKET_LABEL) {
            if(!empty($formId) && $formDataId == "new") {
                $packetTemplates = $this->getPacketTemplates($formId);

                if(!empty($packetTemplates) && count($packetTemplates) === 1) {
                    $formTemplateDatas = $packetTemplates[0]['form_items'];

                    foreach ($formTemplateDatas as $ftData) {

                        // Form field data
                        $formFieldData1 = isset($formFieldData[$ftData['id']]) ? $formFieldData[$ftData['id']]['data'] : array();

                        $templateSchema = isset($ftData['template_content']) ? json_decode($ftData['template_content'], true) : array();

                        $templateFieldTree = $this->printAllValues($templateSchema['components']);
                        foreach ($templateFieldTree as $tfItem) {

                            if(isset($tfItem['validate']) && isset($tfItem['validate']['requiredBeforeSending']) && $tfItem['validate']['requiredBeforeSending'] == "1" && (!isset($formFieldData1[$tfItem['key']]) || empty($formFieldData1[$tfItem['key']]))) {

                                // Set required form data
                                if(!isset($reqFieldFormData[$ftData['id']])) {
                                    $reqFieldFormData[$ftData['id']] = array(
                                        "form" => $ftData       
                                    );
                                }

                                // Set required fields
                                $reqFieldFormData[$ftData['id']]['req_fields'][] = $tfItem['key'];

                                $reqBeforeSendingFieldList[] = $ftData['template_name'] . " - " . $tfItem['label'];
                            }
                        }
                    }
                    
                }
            }
        }

        if(!empty($reqFieldFormData) && !empty($reqBeforeSendingFieldList)) {
            return array('req_fields' => $reqBeforeSendingFieldList, 'req_form' => $reqFieldFormData);
        }

        return false;
    }
}