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
use Vitalhealthcare\OpenEMR\Modules\Generic\Controller\UberController;
use RestConfig;

class UberRestController
{
	/**
     * @var CustomSkeletonFHIRResourceService
     */
    private $customSkeletonResourceService;

    /**
     * @var FhirResourcesService
     */
    private $fhirService;

    public function __construct()
    {
        $this->fhirService = new FhirResourcesService();
    }

    public function uberWebhook(HttpRestRequest $request)
    {
    	try {
    		if ($_SERVER['REQUEST_METHOD'] == 'POST'){
	            $data = file_get_contents("php://input");
	            $decode = json_decode($data ?? "", true);
	            $metaInfo = $decode['meta'] ?? array();

		        $event_type = $decode['event_type'] ?? "";
		        $resource_id = $metaInfo['resource_id'] ?? "";
		        $status = $metaInfo['status'] ?? "";

		        if (empty($_REQUEST['action'] ?? "")) {
		        	throw new \Exception("Not valid action");
		        }
				
				if (empty($decode)) {
					throw new \Exception("Empty event data");
				}

				if (empty($event_type)) {
					throw new \Exception("Empty event_type");
				}

				if (empty($resource_id)) {
					throw new \Exception("Empty resource_id");
				}
				
	            if (isset($_REQUEST['action'])) {
		            if ($_REQUEST['action'] == "status_changed") {
			            if ($event_type == "health.status_changed") {

			            	// Create uber controller
							$ubController = new UberController();

							$ubController->saveTripDetails($resource_id);
			            }
		        	} else if ($_REQUEST['action'] == "driver_location") {
		        		if ($event_type == "health.driver_location") {
		        			$dataInfo = $decode['data'] ?? array();
		        			$locationInfo = $dataInfo['location'] ?? array();

		        			// Create uber controller
							$ubController = new UberController();

							$ubController->updateDriverLocationDetails($resource_id, $locationInfo);
		        		}
		        	}
	        	}
	        }

        } catch (\Throwable $e) {
			http_response_code(400); // Bad Request
			die($e->getMessage());
		}

		 exit();
    }
}