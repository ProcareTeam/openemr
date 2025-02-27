<?php
/**
 * FHIR Resource Controller example for handling and responding to
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Hardik Khatri
 */

namespace Vitalhealthcare\OpenEMR\Modules\Generic\Api;

use OpenEMR\Common\Http\HttpRestRequest;
use OpenEMR\Common\Http\HttpRestRouteHandler;
use OpenEMR\FHIR\R4\FHIRResource\FHIRBundle;
use OpenEMR\FHIR\R4\FHIRResource\FHIRBundle\FHIRBundleEntry;
use OpenEMR\RestControllers\RestControllerHelper;
use OpenEMR\Services\FHIR\FhirResourcesService;
use OpenEMR\Validators\ProcessingResult;
use Psr\Http\Message\ResponseInterface;
use OpenEMR\RestControllers\FHIR\FhirPatientRestController;
use OpenEMR\RestControllers\PatientRestController;
use OpenEMR\Services\PatientService;

class PortalRestController
{
	public function __construct()
    {
    }

    public function getAssignedPatients(HttpRestRequest $request)
    {
        $searchParams = $request->getQueryParams();
        $processingResult = new ProcessingResult();

        $emailParam = isset($searchParams["email"]) && $searchParams["email"] != "" ? $searchParams["email"] : "";
        $pageParam = isset($searchParams["page"]) && $searchParams["page"] != "" ? $searchParams["page"] : 1;
        $limitParam = isset($searchParams["limit"]) && $searchParams["limit"] != "" ? $searchParams["limit"] : 10;

        $searchParam = isset($searchParams["search"]) && $searchParams["search"] != "" ? $searchParams["search"] : "";

        $sortingColumn = isset($searchParams["sorting_column"]) && $searchParams["sorting_column"] != "" ? $searchParams["sorting_column"] : "";

        $sortingOrder = isset($searchParams["sorting_order"]) && $searchParams["sorting_order"] != "" ? $searchParams["sorting_order"] : "";

        // Calculate the offset for the SQL query
		$pageOffset = ($pageParam - 1) * $limitParam;


        try {

            if (empty($emailParam)) {
            	throw new \Exception("Empty email id");
            }

            $patientIds = array();
            $payerIds = array();

            $userRes = sqlStatement("SELECT vap.* from vh_assign_patients vap join users u on u.id = vap.user_id where u.email = ?", array($emailParam));

            while ($userRow = sqlFetchArray($userRes)) {

            	if ($userRow['type'] == "patient" && $userRow['action'] == "i") {
            		$patientIds[] = $userRow['a_id'];
            	} else if ($userRow['type'] == "payer" && $userRow['action'] == "i") {
            		$payerIds[] = $userRow['a_id'];
            	}
            	
            }

            if (!empty($patientIds) || !empty($payerIds)) {

	            $patientSql = " SELECT pd.fname, pd.lname, CONCAT(CONCAT_WS('', IF(LENGTH(pd.lname),pd.lname,NULL), IF(LENGTH(pd.fname), CONCAT(', ',pd.fname),NULL))) as patient_name, pd.pid, pd.pubpid, pd.DOB as dob, pd.email_direct as email, pd.phone_cell as phone, pd.sex as gender, CONCAT(SUBSTRING(HEX(pd.uuid), 1, 8), '-',SUBSTRING(HEX(pd.uuid), 9, 4), '-',SUBSTRING(HEX(pd.uuid), 13, 4), '-',SUBSTRING(HEX(pd.uuid), 17, 4), '-',SUBSTRING(HEX(pd.uuid), 21, 12)) as uuid, fc.id as case_id, fc.injury_date, fc.case_dt, fc.closed as is_case_closed from form_cases fc left join patient_data pd on pd.pid = fc.pid ";

	            // Get total records
	            $patientTotalSql = " SELECT count(pd.id) as count from form_cases fc left join patient_data pd on pd.pid = fc.pid ";

	            $whereSql = array();

	            if(!empty($patientIds) || !empty($payerIds)) {
	            	$subWhereSql = "";

	            	if (!empty($payerIds)) {
	            		$subWhereSql = " (id1.provider in (" . implode(',', $payerIds) . ") OR id2.provider in (" . implode(',', $payerIds) . ") OR id3.provider in (" . implode(',', $payerIds) . ")) ";
	            	}

	            	if(!empty($patientIds)) {
	            		$subWhereSql .= (!empty($subWhereSql) ? " OR " : "") . "(fc.pid IN (" . implode(',', $patientIds) . ")) ";
	            	}

	            	if (!empty($subWhereSql)) {
	            		$whereSql[] = "fc.id in (SELECT fc.id from form_cases fc left join insurance_data id1 on id1.id = ins_data_id1 left join insurance_data id2 on id2.id = ins_data_id2 left join insurance_data id3 on id3.id = ins_data_id3 where " . $subWhereSql . ")";
	            	}
	        	}


	            $whereSql = "( " . implode(" or ", $whereSql) . " )";

	            if(!empty($searchParam)) {
	            	$whereSql .= " AND ( (pd.fname LIKE '" . $searchParam . "%') OR (pd.lname LIKE '" . $searchParam . "%') OR (CONCAT(CONCAT_WS('', IF(LENGTH(pd.lname),pd.lname,NULL), IF(LENGTH(pd.fname), CONCAT(' ',pd.fname),NULL))) LIKE '" . $searchParam . "%') OR (pd.pubpid LIKE '" . $searchParam . "%' ) OR (pd.pubpid LIKE '" . $searchParam . "%' ) OR (fc.id LIKE '" . $searchParam . "%' ) OR (pd.DOB LIKE '%%" . $searchParam . "%%' ) OR (fc.injury_date LIKE '%%" . $searchParam . "%%' ) OR (fc.case_dt LIKE '%%" . $searchParam . "%%' ) OR (CASE WHEN fc.closed = 0 THEN 'Active' ELSE 'Inactive' END LIKE '" . $searchParam . "%' ) )";
	            }

	            $orderBySql = "";

	            if (!empty($sortingOrder) && !empty($sortingColumn)) {
	            	if ($sortingColumn == "case_date") {
	            		$orderBySql = " ORDER BY fc.case_dt " . $sortingOrder . " ";
	            	} else if ($sortingColumn == "pubpid") {
	            		$orderBySql = " ORDER BY pd.pubpid " . $sortingOrder . " ";
	            	} else if ($sortingColumn == "case_id") {
	            		$orderBySql = " ORDER BY fc.id " . $sortingOrder . " ";
	            	} else if ($sortingColumn == "dob") {
	            		$orderBySql = " ORDER BY pd.DOB " . $sortingOrder . " ";
	            	} else if ($sortingColumn == "patient_name") {
	            		$orderBySql = " ORDER BY (CONCAT(CONCAT_WS('', IF(LENGTH(pd.lname),pd.lname,NULL), IF(LENGTH(pd.fname), CONCAT(', ',pd.fname),NULL)))) " . $sortingOrder . " ";
	            	} else if ($sortingColumn == "injury_date") {
	            		$orderBySql = " ORDER BY fc.injury_date " . $sortingOrder . " ";
	            	} else if ($sortingColumn == "is_case_active") {
	            		$orderBySql = " ORDER BY fc.closed " . $sortingOrder . " ";
	            	}
	            }

	            //$orderBySql = " ORDER BY fc.case_dt DESC ";

	            $patientSql .= " WHERE " . $whereSql . $orderBySql . " LIMIT " . $pageOffset . ", " . $limitParam;

	            // Patient Sql
	            $patientTotalSql .= " WHERE " . $whereSql;

	            $patientRes = sqlStatement($patientSql, array());

	            $resData = array(
	            	"items" => array(),
	            	"data" => array(
	            		"total_count" => 0,
	            		"current_page" => $pageParam
	            	)
	            );

	            //$uuidList = array();
	            $pidList = array();
	            //$otherDetails = array();
	            while ($patientRow = sqlFetchArray($patientRes)) {
	            	//$uuidList[] = $patientRow['uuid'];
	            	//$pidList[] = $patientRow['pid'];
	            	//$otherDetails[$patientRow['pid']] = $patientRow['active_case_count'];
	            	$resData["items"][] = $patientRow;
	            }

	            //$return = (new FhirPatientRestController())->getAll(array('_id' => $uuidList));
	            //$return = !empty($return) ? json_decode(json_encode($return, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), true) : array();

	            //$patientService = new PatientService();
	            //$processingResult = $patientService->getAll(array('pid' => $pidList), false);

	            // $tempItemsData = $processingResult->getData();
	            // foreach ($tempItemsData as $ak => $tItem) {
	            // 	if (isset($tItem['pid']) && !empty($tItem['pid']) && isset($otherDetails[$tItem['pid']])) {
	            // 		$tempItemsData[$ak]['active_case_count'] = $otherDetails[$tItem['pid']];
	            // 	}
	            // }

	            // $processingResult->setData($tempItemsData);

	            //$processingResultResponce = RestControllerHelper::handleProcessingResult($processingResult, 200, true);

	            if (!empty($resData)) {
	            	// Set total count
		            $totalRecords = sqlQuery($patientTotalSql);

		            if (!empty($totalRecords) && $totalRecords['count']) {
		            	$resData['data']['total_count'] = $totalRecords['count'];
		        	}
	            }

	            $processingResult->setData($resData);
        	} 

        } catch (\Throwable $e) {
            return $this->accessDeniedError($e->getMessage());
            exit();
        }

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, 200, true);
        return $responseBody;
    }

    private function accessDeniedError($error = "Unauthorized", $status = 401) {
        $errorData = unserialize($error);
        if (!is_array($errorData)) {
            $errorData = $error;
        }

        return RestControllerHelper::responseHandler(array(
            "error" => "access_denied",
            "error_description" => $errorData,
            "message" => $errorData
        ), null, $status);
    }
}