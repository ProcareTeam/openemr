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
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\FormController;
use Mpdf\Mpdf;

class PatientFormController
{
    /**
     * @var CustomSkeletonFHIRResourceService
     */
    private $customSkeletonResourceService;

    /**
     * @var FhirResourcesService
     */
    private $fhirService;
    private $formController;

    public function __construct()
    {
        $this->fhirService = new FhirResourcesService();
        $this->formController = new FormController();
    }

    public function formAuthCheck(HttpRestRequest $request) {
        $searchParams = $request->getQueryParams();

        $processingResult = new ProcessingResult();
        $patientData = array();

        try {

            $authData = $this->formController->verifyToken();
            $patientData = array(
                "pid" => $authData["pid"],
                "fname" => $authData["patient"]["fname"],
                "lname" => $authData["patient"]["lname"],
                "mname" => $authData["patient"]["mname"]
            );

        } catch (\Throwable $e) {
            return $this->accessDeniedError($e->getMessage());
            exit();
        }

        if(!empty($patientData) && count($patientData) > 0) {
            $processingResult->addData($patientData);
        }

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, 200);
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

    public function savePatientForm(HttpRestRequest $request) {
        $bodyJSONParams = $request->getRequestBodyJSON();
        $processingResult = new ProcessingResult();
        $searchParams = $request->getQueryParams();

        $formIndexParam = isset($searchParams["f"]) && $searchParams["f"] > 0 ? $searchParams["f"] : 1;
        $formIndex = !empty($formIndexParam) && $formIndexParam > 0 ? ($formIndexParam - 1) : 0;

        try {
            $authData = $this->formController->verifyToken();
            $patientData = array(
                "pid" => $authData["pid"],
                "fname" => $authData["patient"]["fname"],
                "lname" => $authData["patient"]["lname"],
                "mname" => $authData["patient"]["mname"]
            );

            $tokenId = isset($authData['tokenId']) ? $authData['tokenId'] : "";
            $authPid = isset($authData['pid']) ? $authData['pid'] : "";
            //$formId = isset($authData['formId']) ? $authData['formId'] : "";
            $formId = isset($authData['formId']) && !empty($authData['formId']) && isset($authData['formId'][$formIndex]) ? $authData['formId'][$formIndex] : "";

            if(empty($authPid) || empty($formId) || empty($tokenId)) {
                throw new \Exception("Unable to submit form");
            }

        } catch (\Throwable $e) {
            return $this->accessDeniedError($e->getMessage());
            exit();
        }

        try {
            if(isset($authData) && !empty($authData)) {

                $bodyData = isset($bodyJSONParams['data']) ? json_decode(json_encode($bodyJSONParams['data']), true) : array();
                $bodyAction = isset($bodyJSONParams['action']) ? $bodyJSONParams['action'] : "";

                if(empty($bodyAction) || !in_array($bodyAction, array("save", "submit", "submit_all")) || empty($bodyData)) {
                    throw new \Exception("Bad Operation");
                }

                $saveResult = $this->formController->savePatientForm($bodyData, $bodyAction, $formId, $authPid, $tokenId);

                if(empty($saveResult)) {
                    throw new \Exception("Empty Result");
                }

                $processingResult->addData($saveResult);
            }

        } catch (\Throwable $e) {
            if(in_array($e->getCode(), array(101, 102))) {
                return $this->accessDeniedError($e->getMessage(), 200);
            } else {
                return $this->accessDeniedError($e->getMessage(), 403);
            }
            exit();
        }

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, 200);
        return $responseBody;
    }

    public function patientForm(HttpRestRequest $request)
    {
        $searchParams = $request->getQueryParams();
        $processingResult = new ProcessingResult();

        $formIndexParam = isset($searchParams["f"]) && $searchParams["f"] > 0 ? $searchParams["f"] : 1;
        $formIndex = !empty($formIndexParam) && $formIndexParam > 0 ? ($formIndexParam - 1) : 0;

        try {
            $authData = $this->formController->verifyToken();
            $patientData = array(
                "pid" => $authData["pid"],
                "fname" => $authData["patient"]["fname"],
                "lname" => $authData["patient"]["lname"],
                "mname" => $authData["patient"]["mname"]
            );
        } catch (\Throwable $e) {
            return $this->accessDeniedError($e->getMessage());
            exit();
        }

        try {
            if(isset($authData) && !empty($authData)) {
                $authPid = isset($authData['pid']) ? $authData['pid'] : "";
                $formId = isset($authData['formId']) && !empty($authData['formId']) && isset($authData['formId'][$formIndex]) ? $authData['formId'][$formIndex] : "";

                $otherDetails = array();

                if(count($authData['formId']) > 1 && count($authData['formId']) >= $formIndexParam) {
                    $otherDetails['prev'] = 0;
                    $otherDetails['next'] = 0;
                    $otherDetails['current'] = !empty($formIndexParam) ? (int) $formIndexParam : 0;
                    $otherDetails['total'] = !empty($authData['formId']) ? count($authData['formId']) : 0;

                    if($formIndexParam > 1) $otherDetails['prev'] = $formIndexParam - 1;
                    if(count($authData['formId']) > $formIndexParam) $otherDetails['next'] = $formIndexParam + 1;
                }

                if(empty($authPid) || empty($formId)) {
                    throw new \Exception("Unable to submit form");
                }

                $fullFormData = $this->formController->getFullFormData($authPid, $formId);

                if(!empty($fullFormData) && isset($fullFormData['form_details']) && isset($fullFormData['form_details']['status']) && in_array($fullFormData['form_details']['status'], array(FormController::SAVE_LABEL, FormController::PENDING_LABEL))) {

                    if(!isset($fullFormData['schema']) || empty($fullFormData['schema'])) {
                        throw new \Exception("Form not found");
                    }

                    $processingResult->addData(array(
                        "schema" => isset($fullFormData['schema']) ? $fullFormData['schema'] : array(),
                        "data" => isset($fullFormData['data']) ? $fullFormData['data'] : array(),
                        "form_details" => $fullFormData['form_details'],
                        "other" => $otherDetails,
                        "allowed_lang" => $GLOBALS['fm_form_translate_lang'] ?? ""
                    ));

                } else {
                    throw new \Exception("Unable to submit form");
                }
            }
        } catch (\Throwable $e) {
            return $this->accessDeniedError($e->getMessage(), 403);
            exit();
        }

        $responseBody = RestControllerHelper::handleProcessingResult($processingResult, null, 200);
        return $responseBody;
    }
}